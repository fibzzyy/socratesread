<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $fillable = [
        'reading_session_id',
        'socratic_question_id',
        'answer_text',
        'score',
        'understanding_level',
        'evaluation',
        'answered_at',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'evaluated_at' => 'datetime',
            'evaluation' => 'array',
            'score' => 'integer',
        ];
    }

    public function readingSession(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SocraticQuestion::class, 'socratic_question_id');
    }
}
