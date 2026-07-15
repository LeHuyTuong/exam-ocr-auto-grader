<?php

namespace App\Services\Vision;

class MistralGradedPaperExtractor extends OpenAiCompatibleGradedPaperExtractor
{
    public function __construct()
    {
        parent::__construct(
            apiKey: config('services.mistral.api_key', ''),
            model: config('services.mistral.model', 'mistral-small-latest'),
            baseUrl: 'https://api.mistral.ai/v1',
        );
    }
}
