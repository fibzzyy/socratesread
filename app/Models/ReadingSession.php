<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingSession extends Model
{
    protected $fillable = ['user_id', 'document_id', 'status', 'progress_percent', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function coachMessages(): HasMany
    {
        return $this->hasMany(ReadingCoachMessage::class)->orderBy('id');
    }

    public function learningActivities(): HasMany
    {
        return $this->hasMany(LearningActivity::class);
    }
}
