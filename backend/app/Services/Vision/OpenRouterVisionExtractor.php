<?php

namespace App\Services\Vision;

class OpenRouterVisionExtractor extends OpenAiCompatibleVisionExtractor
{
    public function __construct()
    {
        parent::__construct(
            apiKey: config('services.openrouter.api_key', ''),
            model: config('services.openrouter.model', 'google/gemma-4-31b-it:free'),
            baseUrl: 'https://openrouter.ai/api/v1',
        );
    }
}
