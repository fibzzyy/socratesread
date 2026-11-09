<?php

namespace App\Services\AI\Contracts;

interface LanguageModel
{
    public function isConfigured(): bool;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function respond(array $messages, string $instructions, array $options = []): string;
}
