<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingCoachMessage extends Model
{
    protected $fillable = [
        'reading_session_id',
        'user_id',
        'document_id',
        'role',
        'content',
        'is_meaningful',
    ];

    protected function casts(): array
    {
        return [
            'is_meaningful' => 'boolean',
        ];
    }

    public function readingSession(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
