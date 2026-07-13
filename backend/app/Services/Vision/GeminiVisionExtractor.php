<?php

namespace App\Services\Vision;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiVisionExtractor implements VisionExtractor
{
    /** @var string[] */
    private array $apiKeys;

    private string $model;

    public function __construct()
    {
        $this->apiKeys = config('services.gemini.api_keys', []);
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');
    }

    public function extract(string $imageBytes, ?string $mlkitHint): ExtractResult
    {
        if (empty($this->apiKeys)) {
            throw new \RuntimeException('No Gemini API keys configured');
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->buildPrompt($mlkitHint)],
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
                        'totalCorrect' => ['type' => 'INTEGER'],
                        'confidence' => ['type' => 'NUMBER'],
                    ],
                    'required' => ['studentName', 'totalCorrect', 'confidence'],
                ],
            ],
        ];

        $lastError = 'unknown error';

        foreach ($this->apiKeys as $apiKey) {
            $response = Http::timeout(15)
                ->retry(1, 2000, function (\Exception $e) {
                    return $e instanceof ConnectionException;
                })
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $apiKey,
                ])
                ->post($this->url(), $body);

            if ($response->successful()) {
                return $this->parse($response->json());
            }

            $lastError = $response->json('error.message') ?? $response->body();
        }

        throw new \RuntimeException('Gemini API error (all keys exhausted): '.$lastError);
    }

    private function parse(?array $json): ExtractResult
    {
        $text = data_get($json, 'candidates.0.content.parts.0.text');

        if (! $text) {
            throw new \RuntimeException('Gemini returned empty response');
        }

        $data = json_decode($text, true);

        if (! $data || ! isset($data['studentName'], $data['totalCorrect'], $data['confidence'])) {
            throw new \RuntimeException('Gemini returned invalid JSON: '.$text);
        }

        return new ExtractResult(
            studentName: trim($data['studentName']),
            totalCorrect: (int) $data['totalCorrect'],
            confidence: (float) $data['confidence'],
        );
    }

    private function url(): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    private function buildPrompt(?string $mlkitHint): string
    {
        $hint = $mlkitHint
            ? "Gợi ý từ OCR: \"{$mlkitHint}\". Hãy dùng nếu ảnh khó đọc."
            : '';

        return "Bạn là trợ lý chấm bài thi. Hãy đọc tên học sinh và tổng số câu đúng từ ảnh bài thi.
Trả về JSON với các trường: studentName (tên học sinh), totalCorrect (số câu đúng, là số nguyên), confidence (độ tin cậy 0-1).
Chỉ trả về JSON, không thêm text nào khác.
{$hint}";
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
