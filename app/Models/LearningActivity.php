<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningActivity extends Model
{
    protected $fillable = ['user_id', 'reading_session_id', 'document_id', 'event_type', 'xp_awarded', 'summary', 'activity_date'];

    protected function casts(): array
    {
        return ['activity_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readingSession(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class);
    }
}
