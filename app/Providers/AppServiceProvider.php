<?php

namespace App\Providers;

use App\Services\AI\Contracts\LanguageModel;
use App\Services\AI\OpenRouterResponsesProvider;
use RuntimeException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LanguageModel::class, function () {
            return match (config('ai.provider')) {
                'openrouter' => $this->app->make(OpenRouterResponsesProvider::class),
                default => throw new RuntimeException('AI provider is not supported.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production' || isset($_ENV['VERCEL']) || getenv('VERCEL')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
