<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Http;

abstract class OpenAiCompatibleGradedPaperExtractor implements GradedPaperExtractor
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

    public function extract(string $imageBytes, string $mode, ?string $mlkitHint): GradedPaperResult
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
                            ['type' => 'text', 'text' => GradedPaperPrompt::build($mode, $mlkitHint)],
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

        return $this->parse($response->json(), $mode);
    }

    private function parse(?array $json, string $mode): GradedPaperResult
    {
        $text = data_get($json, 'choices.0.message.content');

        if (! $text) {
            throw new \RuntimeException('API returned empty response');
        }

        preg_match('/\{.*\}/s', $text, $matches);
        $data = json_decode($matches[0] ?? '{}', true);

        if (! $data || ! isset($data['confidence'])) {
            throw new \RuntimeException('API returned invalid JSON: '.$text);
        }

        if ($mode === 'name') {
            if (! isset($data['studentName'])) {
                throw new \RuntimeException('API returned invalid JSON: '.$text);
            }

            return new GradedPaperResult(
                studentName: trim($data['studentName']),
                totalScore: null,
                subScores: null,
                confidence: (float) $data['confidence'],
            );
        }

        if (! isset($data['subScores'], $data['totalScore'])) {
            throw new \RuntimeException('API returned invalid JSON: '.$text);
        }

        return new GradedPaperResult(
            studentName: null,
            totalScore: (int) $data['totalScore'],
            subScores: [
                'vocabulary' => (int) ($data['subScores']['vocabulary'] ?? 0),
                'grammar' => (int) ($data['subScores']['grammar'] ?? 0),
                'listening' => (int) ($data['subScores']['listening'] ?? 0),
                'reading' => (int) ($data['subScores']['reading'] ?? 0),
                'writing' => (int) ($data['subScores']['writing'] ?? 0),
                'speaking' => (int) ($data['subScores']['speaking'] ?? 0),
            ],
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
