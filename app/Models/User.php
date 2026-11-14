<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_path',
        'school_origin',
        'nip_nuptk',
        'teacher_identifier_type',
        'subject_area',
        'bio',
        'nisn',
        'entry_grade',
        'enrollment_year',
        'graduation_year',
        'otp_code',
        'otp_expires_at',
        'email_verified_at',
    ];

    public function readingSessions(): HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    public function learningActivities(): HasMany
    {
        return $this->hasMany(LearningActivity::class);
    }

    public function teacherChallengeSubmissions(): HasMany
    {
        return $this->hasMany(StudentTeacherChallengeSubmission::class);
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function teacherClasses(): HasMany
    {
        return $this->hasMany(TeacherClass::class, 'teacher_id');
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(TeacherClass::class, 'classroom_memberships', 'student_id', 'classroom_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return route('avatar', ['user' => $this->id, 'v' => $this->updated_at?->timestamp]);
    }

    public function initials(): string
    {
        return mb_strtoupper(
            collect(explode(' ', (string) $this->name))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode('')
        ) ?: 'U';
    }

    /**
     * Hitung tahun kelulusan otomatis berdasarkan tahun masuk dan jenjang kelas saat mendaftar.
     * Contoh:
     * - Masuk Kelas 10 tahun 2025: 2025 + (12 - 10) = 2027 (Akses 3 tahun: 2025, 2026, 2027)
     * - Masuk Kelas 11 tahun 2026: 2026 + (12 - 11) = 2027 (Akses 2 tahun: 2026, 2027)
     * - Masuk Kelas 12 tahun 2027: 2027 + (12 - 12) = 2027 (Akses 1 tahun: 2027)
     */
    public static function calculateGraduationYear(int $enrollmentYear, int $entryGrade): int
    {
        $clampedGrade = max(10, min(12, $entryGrade));
        return $enrollmentYear + (12 - $clampedGrade);
    }

    /**
     * Menghitung tingkat kelas berjalan (current grade) secara dinamis mengikuti tahun berjalan.
     */
    public function currentGrade(): ?int
    {
        if ($this->entry_grade === null) {
            return null;
        }

        $currentYear = (int) now()->year;
        $enrollmentYear = (int) ($this->enrollment_year ?? $currentYear);
        $yearsElapsed = max(0, $currentYear - $enrollmentYear);

        return min(12, (int) $this->entry_grade + $yearsElapsed);
    }

    /**
     * Mengecek apakah jenjang kelas yang diminta adalah jenjang sebelum siswa terdaftar.
     * Contoh: Siswa pindahan mendaftar di Kelas 11 -> isPriorGrade(10) bernilai true.
     */
    public function isPriorGrade(?int $grade): bool
    {
        if ($grade === null || $this->entry_grade === null) {
            return false;
        }

        return $grade < (int) $this->entry_grade;
    }

    /**
     * Daftar tahun ajaran yang sah diakses siswa selama masa sekolahnya.
     * @return array<int>
     */
    public function accessibleYears(): array
    {
        if (! $this->enrollment_year || ! $this->graduation_year) {
            return [];
        }

        return range((int) $this->enrollment_year, (int) $this->graduation_year);
    }

    /**
     * Mengecek apakah siswa masih aktif bersekolah (belum melewati tahun kelulusan).
     */
    public function isActiveStudent(): bool
    {
        if ($this->role !== 'student') {
            return false;
        }

        if (! $this->graduation_year) {
            return true;
        }

        return (int) now()->year <= (int) $this->graduation_year;
    }

    /**
     * Mengecek apakah siswa sudah lulus (Alumni).
     */
    public function isAlumni(): bool
    {
        return $this->role === 'student' && ! $this->isActiveStudent();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'entry_grade' => 'integer',
            'enrollment_year' => 'integer',
            'graduation_year' => 'integer',
        ];
    }
}
