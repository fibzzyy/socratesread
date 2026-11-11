<?php

return [
    'provider' => env('AI_PROVIDER', 'openrouter'),
    'limits' => [
        'requests_per_day' => (int) env('AI_REQUESTS_PER_DAY', 12),
        'message_characters' => (int) env('AI_MESSAGE_CHARACTERS', 1200),
        'history_turns' => (int) env('AI_HISTORY_TURNS', 6),
        'history_characters' => (int) env('AI_HISTORY_CHARACTERS', 600),
        'passage_characters' => (int) env('AI_PASSAGE_CHARACTERS', 6000),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 300),
        'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 60),
    ],
];
