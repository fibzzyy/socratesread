<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeacherClass extends Model
{
    protected $fillable = ['teacher_id', 'name', 'target_grade', 'join_code', 'is_active'];

    protected function casts(): array
    {
        return [
            'target_grade' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function targetGradeLabel(): string
    {
        return $this->target_grade ? "Kelas {$this->target_grade}" : 'Semua Tingkat';
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_memberships', 'classroom_id', 'student_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }
}
