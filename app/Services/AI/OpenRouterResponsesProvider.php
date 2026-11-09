<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LanguageModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenRouterResponsesProvider implements LanguageModel
{
    public function isConfigured(): bool
    {
        return is_string(config('services.openrouter.api_key'))
            && trim((string) config('services.openrouter.api_key')) !== '';
    }

    public function respond(array $messages, string $instructions, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenRouter API key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.openrouter.base_url'), '/');
        $timeout = (int) ($options['timeout'] ?? config('ai.limits.timeout_seconds', 60));
        $apiKey = (string) config('services.openrouter.api_key');
        $model = $options['model'] ?? config('services.openrouter.model');

        // 1. Format payload untuk endpoint standard /chat/completions
        $chatMessages = [];
        if (trim($instructions) !== '') {
            $chatMessages[] = [
                'role' => 'system',
                'content' => $instructions,
            ];
        }
        foreach ($messages as $msg) {
            $chatMessages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => (string) ($msg['content'] ?? ''),
            ];
        }

        $chatPayload = [
            'model' => $model,
            'messages' => $chatMessages,
        ];

        if (isset($options['temperature'])) {
            $chatPayload['temperature'] = (float) $options['temperature'];
        }
        if (isset($options['max_output_tokens'])) {
            $chatPayload['max_tokens'] = (int) $options['max_output_tokens'];
        }

        // Coba endpoint standar /chat/completions terlebih dahulu
        try {
            $chatResponse = Http::acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->connectTimeout(10)
                ->timeout($timeout)
                ->post($baseUrl . '/chat/completions', $chatPayload);

            if ($chatResponse->successful()) {
                $content = $chatResponse->json('choices.0.message.content');
                if (is_string($content) && trim($content) !== '') {
                    return trim($content);
                }
                file_put_contents(base_path('chat_error.txt'), "Chat 200 but content empty: " . json_encode($chatResponse->json()));
            } else {
                file_put_contents(base_path('chat_error.txt'), "Chat failed {$chatResponse->status()}: " . $chatResponse->body());
                \Illuminate\Support\Facades\Log::warning('OpenRouter chat/completions unsuccesful: ' . $chatResponse->status(), [
                    'body' => $chatResponse->body(),
                ]);
            }
        } catch (Throwable $e) {
            file_put_contents(base_path('chat_error.txt'), "Chat exception: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::warning('OpenRouter chat/completions exception: ' . $e->getMessage());
        }

        // 2. Fallback ke endpoint legacy /responses jika endpoint chat completions tidak merespons
        $responsesPayload = [
            'model' => $model,
            'instructions' => $instructions,
            'input' => $messages,
            'max_output_tokens' => (int) ($options['max_output_tokens'] ?? config('ai.limits.max_output_tokens', 300)),
        ];

        if (isset($options['temperature'])) {
            $responsesPayload['temperature'] = (float) $options['temperature'];
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->connectTimeout(10)
            ->timeout($timeout)
            ->post($baseUrl . '/responses', $responsesPayload);

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
