<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherChallenge extends Model
{
    protected $fillable = [
        'document_id',
        'type',
        'sequence',
        'question',
        'context',
        'options',
        'correct_answer',
        'rubric',
        'reference_excerpt',
        'difficulty',
        'difficulty_weight',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'options' => 'array',
            'difficulty_weight' => 'integer',
        ];
    }

    public static function weightForDifficulty(?string $difficulty): int
    {
        return match (strtolower((string) $difficulty)) {
            'easy' => 1,
            'hard' => 3,
            default => 2,
        };
    }

    public function difficultyLabel(): string
    {
        return match (strtolower((string) ($this->difficulty ?? 'medium'))) {
            'easy' => 'Easy',
            'hard' => 'Hard',
            default => 'Medium',
        };
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
