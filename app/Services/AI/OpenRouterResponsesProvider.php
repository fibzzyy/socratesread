<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LanguageModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        if (isset($options['reasoning']) && is_array($options['reasoning'])) {
            $chatPayload['reasoning'] = $options['reasoning'];
        }

        $maxAttempts = 2;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $http = Http::acceptJson()
                    ->asJson()
                    ->withToken($apiKey)
                    ->connectTimeout(25)
                    ->timeout($timeout);

                if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
                    $http = $http->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);
                }

                $chatResponse = $http->post($baseUrl . '/chat/completions', $chatPayload);

                if ($chatResponse->successful()) {
                    $content = $chatResponse->json('choices.0.message.content');
                    if (! is_string($content) || trim($content) === '') {
                        $reasoning = $chatResponse->json('choices.0.message.reasoning');
                        if (is_string($reasoning) && trim($reasoning) !== '') {
                            $content = $reasoning;
                        }
                    }

                    if (is_string($content) && trim($content) !== '') {
                        return trim($content);
                    }

                    Log::warning('OpenRouter chat/completions returned empty content.', [
                        'attempt' => $attempt,
                        'model' => $model,
                    ]);

                    if ($attempt < $maxAttempts) {
                        continue;
                    }

                    throw new RuntimeException('OpenRouter returned an empty completion.');
                }

                Log::warning('OpenRouter chat/completions unsuccessful: ' . $chatResponse->status(), [
                    'attempt' => $attempt,
                    'body' => mb_substr($chatResponse->body(), 0, 500),
                ]);

                if (in_array($chatResponse->status(), [404, 405], true)) {
                    break; // Fallback ke /responses
                }

                if (in_array($chatResponse->status(), [429, 500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                    usleep(400000);
                    continue;
                }

                $chatResponse->throw();
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning('OpenRouter chat/completions connection error: ' . $e->getMessage(), [
                    'attempt' => $attempt,
                ]);
                if ($attempt < $maxAttempts) {
                    usleep(300000);
                    continue;
                }
                throw $e;
            } catch (Throwable $e) {
                $lastException = $e;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
            }
        }

        if ($lastException instanceof Throwable && ! (isset($chatResponse) && in_array($chatResponse->status(), [404, 405], true))) {
            throw $lastException;
        }

        // Fallback ke endpoint legacy /responses hanya jika /chat/completions mengembalikan 404/405
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
