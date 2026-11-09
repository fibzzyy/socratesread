<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTeacherChallengeSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'document_id',
        'score',
        'xp_awarded',
        'answers',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'xp_awarded' => 'integer',
            'answers' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
