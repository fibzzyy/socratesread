<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LanguageModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiLanguageModelProvider implements LanguageModel
{
    public function isConfigured(): bool
    {
        return is_string(config('services.gemini.api_key'))
            && trim((string) config('services.gemini.api_key')) !== '';
    }

    public function respond(array $messages, string $instructions, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $apiKey = trim((string) config('services.gemini.api_key'));
        $model = (string) ($options['model'] ?? config('services.gemini.model', 'gemini-2.5-flash'));
        $timeout = (int) ($options['timeout'] ?? config('ai.limits.timeout_seconds', 60));

        $contents = [];
        foreach ($messages as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $text = trim((string) ($msg['content'] ?? ''));
            if ($text !== '') {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
        ];

        if (trim($instructions) !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => trim($instructions)]],
            ];
        }

        $generationConfig = [];
        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = (float) $options['temperature'];
        }
        if (isset($options['max_output_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $options['max_output_tokens'];
        }
        if (! empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        $endpoint = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
        $maxAttempts = 2;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $http = Http::acceptJson()
                    ->asJson()
                    ->connectTimeout(10)
                    ->timeout($timeout);

                if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
                    $http = $http->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);
                }

                $response = $http->post($endpoint, $payload);

                if ($response->successful()) {
                    $parts = $response->json('candidates.0.content.parts');
                    if (is_array($parts)) {
                        $combined = implode('', array_map(
                            fn ($p) => is_array($p) ? (string) ($p['text'] ?? '') : '',
                            $parts
                        ));
                        if (trim($combined) !== '') {
                            return trim($combined);
                        }
                    }

                    Log::warning('Gemini generateContent returned empty text.', [
                        'attempt' => $attempt,
                        'model' => $model,
                    ]);

                    if ($attempt < $maxAttempts) {
                        continue;
                    }

                    throw new RuntimeException('Gemini returned an empty completion.');
                }

                $status = $response->status();
                $body = $response->body();

                Log::warning('Gemini generateContent request failed.', [
                    'attempt' => $attempt,
                    'status' => $status,
                    'model' => $model,
                    'body' => mb_substr($body, 0, 300),
                ]);

                if (in_array($status, [429, 500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                    usleep(400000);
                    continue;
                }

                throw new RuntimeException("Gemini API request failed with status {$status}.");
            } catch (ConnectionException $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    usleep(400000);
                    continue;
                }
            } catch (Throwable $e) {
                $lastException = $e;
                break;
            }
        }

        throw $lastException ?? new RuntimeException('Gemini API request failed unexpectedly.');
    }
}
