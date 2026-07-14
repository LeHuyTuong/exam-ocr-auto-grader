<?php

namespace App\Providers;

use App\Services\Vision\AnswerSheetExtractor;
use App\Services\Vision\AnswerSheetExtractorChain;
use App\Services\Vision\GeminiAnswerSheetExtractor;
use App\Services\Vision\GeminiVisionExtractor;
use App\Services\Vision\GroqAnswerSheetExtractor;
use App\Services\Vision\GroqVisionExtractor;
use App\Services\Vision\MistralAnswerSheetExtractor;
use App\Services\Vision\MistralVisionExtractor;
use App\Services\Vision\OpenRouterAnswerSheetExtractor;
use App\Services\Vision\OpenRouterVisionExtractor;
use App\Services\Vision\VisionExtractor;
use App\Services\Vision\VisionExtractorChain;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Chỉ nạp provider nào đã cấu hình API key — tránh khởi tạo với key null
        // (vỡ trên CI / khi thiếu key) và cho phép chạy với bất kỳ tập provider nào.
        $this->app->bind(VisionExtractor::class, function () {
            $extractors = [];
            if (! empty(config('services.gemini.api_keys'))) {
                $extractors[] = new GeminiVisionExtractor;
            }
            if (config('services.groq.api_key')) {
                $extractors[] = new GroqVisionExtractor;
            }
            if (config('services.mistral.api_key')) {
                $extractors[] = new MistralVisionExtractor;
            }
            if (config('services.openrouter.api_key')) {
                $extractors[] = new OpenRouterVisionExtractor;
            }

            return new VisionExtractorChain($extractors);
        });

        $this->app->bind(AnswerSheetExtractor::class, function () {
            $extractors = [];
            if (! empty(config('services.gemini.api_keys'))) {
                $extractors[] = new GeminiAnswerSheetExtractor;
            }
            if (config('services.groq.api_key')) {
                $extractors[] = new GroqAnswerSheetExtractor;
            }
            if (config('services.mistral.api_key')) {
                $extractors[] = new MistralAnswerSheetExtractor;
            }
            if (config('services.openrouter.api_key')) {
                $extractors[] = new OpenRouterAnswerSheetExtractor;
            }

            return new AnswerSheetExtractorChain($extractors);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
