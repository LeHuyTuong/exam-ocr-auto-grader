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

    public function extractAnswers(string $imageBytes, array $pageSpec, ?string $mlkitHint, int $attempt = 1): PageAnswers
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
                            ['type' => 'text', 'text' => AnswerSheetPrompt::build($pageSpec, $mlkitHint, $attempt)],
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
