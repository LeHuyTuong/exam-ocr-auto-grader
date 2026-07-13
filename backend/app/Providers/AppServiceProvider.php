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
        $this->app->bind(VisionExtractor::class, function () {
            return new VisionExtractorChain([
                new GeminiVisionExtractor,
                new GroqVisionExtractor,
                new MistralVisionExtractor,
                new OpenRouterVisionExtractor,
            ]);
        });

        $this->app->bind(AnswerSheetExtractor::class, function () {
            return new AnswerSheetExtractorChain([
                new GeminiAnswerSheetExtractor,
                new GroqAnswerSheetExtractor,
                new MistralAnswerSheetExtractor,
                new OpenRouterAnswerSheetExtractor,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
