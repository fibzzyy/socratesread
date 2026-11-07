<?php

namespace App\Services\AI\Contracts;

interface LanguageModel
{
    public function isConfigured(): bool;

    /** @param array<int, array{role: string, content: string}> $messages */
    public function respond(array $messages, string $instructions): string;
}
