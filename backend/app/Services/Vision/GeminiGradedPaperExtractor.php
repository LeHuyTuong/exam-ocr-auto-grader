<?php

namespace App\Services\Vision;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiGradedPaperExtractor implements GradedPaperExtractor
{
    /** @var string[] */
    private array $apiKeys;

    private string $model;

    public function __construct()
    {
        $this->apiKeys = config('services.gemini.api_keys', []);
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');
    }

    public function extract(string $imageBytes, string $mode, ?string $mlkitHint): GradedPaperResult
    {
        if (empty($this->apiKeys)) {
            throw new \RuntimeException('No Gemini API keys configured');
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => GradedPaperPrompt::build($mode, $mlkitHint)],
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
                'response_schema' => $this->schemaFor($mode),
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
                return $this->parse($response->json(), $mode);
            }

            $lastError = $response->json('error.message') ?? $response->body();
        }

        throw new \RuntimeException('Gemini API error (all keys exhausted): '.$lastError);
    }

    private function schemaFor(string $mode): array
    {
        if ($mode === 'name') {
            return [
                'type' => 'OBJECT',
                'properties' => [
                    'studentName' => ['type' => 'STRING'],
                    'confidence' => ['type' => 'NUMBER'],
                ],
                'required' => ['studentName', 'confidence'],
            ];
        }

        return [
            'type' => 'OBJECT',
            'properties' => [
                // NUMBER chứ không INTEGER: điểm chấm tay hay có nửa điểm
                // ("7.5") — schema INTEGER buộc model làm tròn trước khi trả về.
                'subScores' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'vocabulary' => ['type' => 'NUMBER'],
                        'grammar' => ['type' => 'NUMBER'],
                        'listening' => ['type' => 'NUMBER'],
                        'reading' => ['type' => 'NUMBER'],
                        'writing' => ['type' => 'NUMBER'],
                        'speaking' => ['type' => 'NUMBER'],
                    ],
                    'required' => ['vocabulary', 'grammar', 'listening', 'reading', 'writing', 'speaking'],
                ],
                'totalScore' => ['type' => 'NUMBER'],
                'confidence' => ['type' => 'NUMBER'],
            ],
            'required' => ['subScores', 'totalScore', 'confidence'],
        ];
    }

    private function parse(?array $json, string $mode): GradedPaperResult
    {
        $text = data_get($json, 'candidates.0.content.parts.0.text');

        if (! $text) {
            throw new \RuntimeException('Gemini returned empty response');
        }

        $data = json_decode($text, true);

        if (! $data || ! isset($data['confidence'])) {
            throw new \RuntimeException('Gemini returned invalid JSON: '.$text);
        }

        if ($mode === 'name') {
            if (! isset($data['studentName'])) {
                throw new \RuntimeException('Gemini returned invalid JSON: '.$text);
            }

            return new GradedPaperResult(
                studentName: trim($data['studentName']),
                totalScore: null,
                subScores: null,
                confidence: (float) $data['confidence'],
            );
        }

        if (! isset($data['subScores'], $data['totalScore'])) {
            throw new \RuntimeException('Gemini returned invalid JSON: '.$text);
        }

        return new GradedPaperResult(
            studentName: null,
            totalScore: round((float) $data['totalScore'], 2),
            subScores: [
                'vocabulary' => round((float) ($data['subScores']['vocabulary'] ?? 0), 2),
                'grammar' => round((float) ($data['subScores']['grammar'] ?? 0), 2),
                'listening' => round((float) ($data['subScores']['listening'] ?? 0), 2),
                'reading' => round((float) ($data['subScores']['reading'] ?? 0), 2),
                'writing' => round((float) ($data['subScores']['writing'] ?? 0), 2),
                'speaking' => round((float) ($data['subScores']['speaking'] ?? 0), 2),
            ],
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
