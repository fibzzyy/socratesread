<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'title',
        'category',
        'target_grade',
        'excerpt',
        'body',
        'source_file_path',
        'uploaded_by',
        'estimated_minutes',
        'quiz_duration_minutes',
        'quiz_question_count',
        'is_published',
    ];

    public function targetGradeLabel(): string
    {
        return match ((string) $this->target_grade) {
            '10' => 'Kelas 10',
            '11' => 'Kelas 11',
            '12' => 'Kelas 12',
            default => 'Semua Kelas',
        };
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'quiz_duration_minutes' => 'integer',
            'quiz_question_count' => 'integer',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SocraticQuestion::class)->orderBy('sequence');
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(DocumentQuizQuestion::class)->orderBy('sequence');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(StudentQuizAttempt::class);
    }

    public function readingSessions(): HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function teacherChallenges(): HasMany
    {
        return $this->hasMany(TeacherChallenge::class)->orderBy('sequence');
    }

    public function teacherChallengeSubmissions(): HasMany
    {
        return $this->hasMany(StudentTeacherChallengeSubmission::class);
    }
}
