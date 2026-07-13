<?php

namespace App\Services\Vision;

class GroqVisionExtractor extends OpenAiCompatibleVisionExtractor
{
    public function __construct()
    {
        parent::__construct(
            apiKey: config('services.groq.api_key', ''),
            model: config('services.groq.model', 'meta-llama/llama-4-scout-17b-16e-instruct'),
            baseUrl: 'https://api.groq.com/openai/v1',
        );
    }
}
