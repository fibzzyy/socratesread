<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LanguageModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterResponsesProvider implements LanguageModel
{
    public function isConfigured(): bool
    {
        return is_string(config('services.openrouter.api_key'))
            && trim((string) config('services.openrouter.api_key')) !== '';
    }

    public function respond(array $messages, string $instructions): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenRouter API key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.openrouter.base_url'), '/');
        $response = Http::acceptJson()
            ->asJson()
            ->withToken((string) config('services.openrouter.api_key'))
            ->connectTimeout(6)
            ->timeout((int) config('ai.limits.timeout_seconds', 25))
            ->post($baseUrl.'/responses', [
                'model' => config('services.openrouter.model'),
                'instructions' => $instructions,
                'input' => $messages,
                'max_output_tokens' => (int) config('ai.limits.max_output_tokens', 300),
            ]);

        $response->throw();

        $text = trim((string) $response->json('output_text', ''));

        if ($text === '') {
            $text = collect($response->json('output', []))
                ->where('type', 'message')
                ->flatMap(fn (array $item) => $item['content'] ?? [])
                ->filter(fn (array $item) => in_array($item['type'] ?? null, ['output_text', 'text'], true))
                ->pluck('text')
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->implode("\n");
        }

        if ($text === '') {
            throw new RuntimeException('The model returned no text output.');
        }

        return trim($text);
    }
}
