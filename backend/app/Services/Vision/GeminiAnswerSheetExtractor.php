<?php

namespace App\Services\Vision;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiAnswerSheetExtractor implements AnswerSheetExtractor
{
    /** @var string[] */
    private array $apiKeys;

    private string $model;

    public function __construct()
    {
        $this->apiKeys = config('services.gemini.api_keys', []);
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');
    }

    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint): PageAnswers
    {
        if (empty($this->apiKeys)) {
            throw new \RuntimeException('No Gemini API keys configured');
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->buildPrompt($pageSpec, $mlkitHint)],
                        [
                            'inline_data' => [
                                'mime_type' => $this->detectMimeType($imageBytes),
                                'data' => base64_encode($imageBytes),
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'response_mime_type' => 'application/json',
                'response_schema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'studentName' => ['type' => 'STRING'],
                        'confidence' => ['type' => 'NUMBER'],
                        'answers' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'partNumber' => ['type' => 'INTEGER'],
                                    'questionNumber' => ['type' => 'INTEGER'],
                                    'value' => ['type' => 'STRING'],
                                    'confidence' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['partNumber', 'questionNumber', 'value', 'confidence'],
                            ],
                        ],
                    ],
                    'required' => ['answers', 'confidence'],
                ],
            ],
        ];

        $lastError = 'unknown error';

        foreach ($this->apiKeys as $apiKey) {
            $response = Http::timeout(20)
                ->retry(1, 2000, function (\Exception $e) {
                    return $e instanceof ConnectionException;
                })
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $apiKey,
                ])
                ->post($this->url(), $body);

            if ($response->successful()) {
                return $this->parse($response->json(), $pageSpec['pageNumber']);
            }

            $lastError = $response->json('error.message') ?? $response->body();
        }

        throw new \RuntimeException('Gemini API error (all keys exhausted): '.$lastError);
    }

    private function parse(?array $json, int $pageNumber): PageAnswers
    {
        $text = data_get($json, 'candidates.0.content.parts.0.text');

        if (! $text) {
            throw new \RuntimeException('Gemini returned empty response');
        }

        $data = json_decode($text, true);

        if (! $data || ! isset($data['answers']) || ! isset($data['confidence'])) {
            throw new \RuntimeException('Gemini returned invalid JSON: '.$text);
        }

        $answers = [];
        foreach ($data['answers'] as $item) {
            $answers[] = new AnswerItem(
                partNumber: (int) $item['partNumber'],
                questionNumber: (int) $item['questionNumber'],
                value: trim((string) $item['value']),
                confidence: (float) ($item['confidence'] ?? 0.0),
            );
        }

        return new PageAnswers(
            pageNumber: $pageNumber,
            answers: $answers,
            studentName: isset($data['studentName']) ? trim($data['studentName']) : null,
            confidence: (float) $data['confidence'],
        );
    }

    private function url(): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    private function buildPrompt(array $pageSpec, ?string $mlkitHint): string
    {
        $pageNumber = $pageSpec['pageNumber'];
        $parts = $pageSpec['parts'] ?? [];

        $partsDesc = '';
        foreach ($parts as $part) {
            $qNums = array_map(fn ($q) => $q['questionNumber'], $part['questions'] ?? []);
            $qNumsStr = implode(', ', $qNums);
            $partsDesc .= "- Part {$part['partNumber']} ({$part['questionType']}): questions [$qNumsStr]\n";
        }

        $hint = $mlkitHint
            ? "\nML Kit OCR hint (use if readable): \"{$mlkitHint}\""
            : '';

        $nameInstr = $pageNumber === 1
            ? '- "studentName": the student\'s full name written on the paper (may be at top of page). If not found, omit or set to empty string.'
            : '';

        return <<<PROMPT
You are a Cambridge YLE exam assistant. Read the exam page image and extract student answers.

Page number: {$pageNumber}

This page contains the following parts:
{$partsDesc}

Rules:
{$nameInstr}
- For each question in the listed parts, extract the student's answer exactly as written.
- For "mcq_abc": answer is A, B, or C (the letter that is ticked/crossed).
- For "fill_blank": answer is the word or number written.
- For "tick_cross": answer is "tick" (✓) or "cross" (✗).
- For "yes_no": answer is "yes" or "no".
- For "word_from_box", "one_word", "word_order": answer is the word(s) as written.
- Do NOT extract answers for parts not listed on this page.
- If no auto-gradable parts exist on this page, return an empty answers array.
- Set "confidence" (0-1) for each answer based on how clearly the handwriting is readable.
- Set overall "confidence" (0-1) for the whole page.
- Return ONLY valid JSON, no extra text.
{$hint}
PROMPT;
    }

    private function detectMimeType(string $bytes): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $bytes);
        finfo_close($finfo);

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])
            ? $mime
            : 'image/jpeg';
    }
}
