<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Http;

abstract class OpenAiCompatibleAnswerSheetExtractor implements AnswerSheetExtractor
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct(string $apiKey, string $model, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint): PageAnswers
    {
        $mime = $this->detectMimeType($imageBytes);

        $response = Http::timeout(25)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $this->buildPrompt($pageSpec, $mlkitHint)],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mime};base64,".base64_encode($imageBytes),
                                ],
                            ],
                        ],
                    ],
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'API error: '.$response->json('error.message') ?? $response->body()
            );
        }

        return $this->parse($response->json(), $pageSpec['pageNumber']);
    }

    private function parse(?array $json, int $pageNumber): PageAnswers
    {
        $text = data_get($json, 'choices.0.message.content');

        if (! $text) {
            throw new \RuntimeException('API returned empty response');
        }

        preg_match('/\{.*\}/s', $text, $matches);
        $data = json_decode($matches[0] ?? '{}', true);

        if (! $data || ! isset($data['answers']) || ! isset($data['confidence'])) {
            throw new \RuntimeException('API returned invalid JSON: '.$text);
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
- Return ONLY valid JSON in this format: {"answers":[{"partNumber":1,"questionNumber":2,"value":"...","confidence":0.9}],"studentName":"...","confidence":0.9}
- No extra text.
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
