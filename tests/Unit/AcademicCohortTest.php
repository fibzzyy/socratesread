<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCohortTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_graduation_year(): void
    {
        // Masuk Kelas 10 tahun 2025 -> lulus 2027 (Akses: 2025, 2026, 2027 = 3 tahun)
        $this->assertSame(2027, User::calculateGraduationYear(2025, 10));

        // Masuk Kelas 11 tahun 2026 -> lulus 2027 (Akses: 2026, 2027 = 2 tahun)
        $this->assertSame(2027, User::calculateGraduationYear(2026, 11));

        // Masuk Kelas 12 tahun 2027 -> lulus 2027 (Akses: 2027 = 1 tahun)
        $this->assertSame(2027, User::calculateGraduationYear(2027, 12));
    }

    public function test_accessible_years_range(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'entry_grade' => 10,
            'enrollment_year' => 2025,
            'graduation_year' => 2027,
        ]);

        $this->assertSame([2025, 2026, 2027], $student->accessibleYears());
        $this->assertTrue($student->isActiveStudent());
        $this->assertFalse($student->isAlumni());
    }

    public function test_is_prior_grade_detection_for_transfer_students(): void
    {
        // Siswa pindahan mendaftar langsung di Kelas 11
        $transferStudent = User::factory()->create([
            'role' => 'student',
            'entry_grade' => 11,
            'enrollment_year' => 2026,
            'graduation_year' => 2027,
        ]);

        // Kelas 10 adalah kelas sebelum siswa terdaftar -> bernilai TRUE (halaman kosong)
        $this->assertTrue($transferStudent->isPriorGrade(10));

        // Kelas 11 dan 12 adalah kelas sah siswa -> bernilai FALSE
        $this->assertFalse($transferStudent->isPriorGrade(11));
        $this->assertFalse($transferStudent->isPriorGrade(12));
    }

    public function test_alumni_status_detection(): void
    {
        // Siswa angkatan lama yang sudah lulus di tahun 2024
        $alumni = User::factory()->create([
            'role' => 'student',
            'entry_grade' => 10,
            'enrollment_year' => 2022,
            'graduation_year' => 2024,
        ]);

        $this->assertTrue($alumni->isAlumni());
        $this->assertFalse($alumni->isActiveStudent());
    }
}
