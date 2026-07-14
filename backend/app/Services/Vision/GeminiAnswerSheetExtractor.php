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

    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint, int $attempt = 1): PageAnswers
    {
        if (empty($this->apiKeys)) {
            throw new \RuntimeException('No Gemini API keys configured');
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => AnswerSheetPrompt::build($pageSpec, $mlkitHint, $attempt)],
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
