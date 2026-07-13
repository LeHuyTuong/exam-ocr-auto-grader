<?php

namespace App\Services\Vision;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

abstract class OpenAiCompatibleVisionExtractor implements VisionExtractor
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
    ) {}

    public function extract(string $imageBytes, ?string $mlkitHint): ExtractResult
    {
        if (! $this->apiKey) {
            throw new \RuntimeException(static::class.' has no API key configured');
        }

        $mimeType = $this->detectMimeType($imageBytes);
        $dataUrl = "data:{$mimeType};base64,".base64_encode($imageBytes);

        $response = Http::timeout(15)
            ->retry(1, 2000, function (\Exception $e) {
                return $e instanceof ConnectionException;
            })
            ->withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $this->buildPrompt($mlkitHint)],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                static::class.' error: '.($response->json('error.message') ?? $response->body())
            );
        }

        $text = $response->json('choices.0.message.content');

        if (! $text) {
            throw new \RuntimeException(static::class.' returned empty response');
        }

        $data = json_decode($this->extractJson($text), true);

        if (! $data || ! isset($data['studentName'], $data['totalCorrect'], $data['confidence'])) {
            throw new \RuntimeException(static::class.' returned invalid JSON: '.$text);
        }

        return new ExtractResult(
            studentName: trim($data['studentName']),
            totalCorrect: (int) $data['totalCorrect'],
            confidence: (float) $data['confidence'],
        );
    }

    private function extractJson(string $text): string
    {
        return preg_match('/\{.*\}/s', $text, $m) ? $m[0] : $text;
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
