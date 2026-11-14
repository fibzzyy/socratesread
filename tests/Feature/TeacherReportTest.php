<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\StudentQuizAttempt;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_reports(): void
    {
        $response = $this->get(route('teacher.reports'));
        $response->assertRedirect(route('login'));

        $excelResponse = $this->get(route('teacher.reports.export_excel'));
        $excelResponse->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_teacher_reports(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('teacher.reports'));
        $response->assertForbidden();

        $excelResponse = $this->actingAs($student)->get(route('teacher.reports.export_excel'));
        $excelResponse->assertForbidden();
    }

    public function test_teacher_can_view_reports_page_with_empty_state(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Guru Riski']);

        $response = $this->actingAs($teacher)->get(route('teacher.reports'));

        $response->assertOk();
        $response->assertSee('Rekap &amp; Laporan Nilai', false);
        $response->assertSee('Anda belum memiliki kelas');
    }

    public function test_teacher_can_view_score_matrix_for_teacher_challenges(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'name' => 'Pak Budi Hartono',
            'school_origin' => 'SMA Negeri 1 Socrates',
            'nip_nuptk' => '198501012010011001',
        ]);

        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'X MIPA 1',
            'target_grade' => 10,
            'join_code' => 'XMIPA101',
            'is_active' => true,
        ]);

        $student1 = User::factory()->create([
            'name' => 'Ahmad Santoso',
            'nisn' => '0081234567',
            'role' => 'student',
        ]);

        $student2 = User::factory()->create([
            'name' => 'Budi Pratama',
            'nisn' => '0089876543',
            'role' => 'student',
        ]);

        $classroom->students()->attach([
            $student1->id => ['joined_at' => now()],
            $student2->id => ['joined_at' => now()],
        ]);

        $doc1 = Document::query()->create([
            'title' => 'Materi Eksponen dan Logaritma',
            'category' => 'Matematika',
            'target_grade' => 10,
            'body' => 'Pembahasan lengkap eksponen.',
            'uploaded_by' => $teacher->id,
            'is_published' => true,
        ]);

        $doc2 = Document::query()->create([
            'title' => 'Materi Barisan dan Deret',
            'category' => 'Matematika',
            'target_grade' => 10,
            'body' => 'Pembahasan barisan aritmatika dan geometri.',
            'uploaded_by' => $teacher->id,
            'is_published' => true,
        ]);

        // Submissions tantangan guru:
        // Student 1: 90 on doc1, 80 on doc2 -> avg 85 (B, Tuntas)
        StudentTeacherChallengeSubmission::query()->create([
            'user_id' => $student1->id,
            'document_id' => $doc1->id,
            'score' => 90,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        StudentTeacherChallengeSubmission::query()->create([
            'user_id' => $student1->id,
            'document_id' => $doc2->id,
            'score' => 80,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Student 2: 60 on doc1, not completed on doc2 -> avg 60 (C, Perlu Bimbingan)
        StudentTeacherChallengeSubmission::query()->create([
            'user_id' => $student2->id,
            'document_id' => $doc1->id,
            'score' => 60,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.reports', [
            'class_id' => $classroom->id,
            'category' => 'Matematika',
            'type' => 'teacher_challenge',
        ]));

        $response->assertOk();
        $response->assertSee('Ahmad Santoso');
        $response->assertSee('0081234567');
        $response->assertSee('Budi Pratama');
        $response->assertSee('0089876543');
        $response->assertSee('M1: Materi Eksponen');
        $response->assertSee('M2: Materi Barisan');

        // Check scores rendered
        $response->assertSee('90');
        $response->assertSee('80');
        $response->assertSee('60');

        // Check status
        $response->assertSee('Tuntas');
        $response->assertSee('Remedial');
    }

    public function test_teacher_can_view_quiz_and_combined_assessments(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'XI IPS 2',
            'join_code' => 'XIIPS202',
        ]);
        $student = User::factory()->create(['name' => 'Citra Lestari', 'role' => 'student']);
        $classroom->students()->attach($student->id, ['joined_at' => now()]);

        $doc = Document::query()->create([
            'title' => 'Sejarah Peradaban Kuno',
            'category' => 'Sejarah',
            'body' => 'Teks peradaban dunia.',
            'uploaded_by' => $teacher->id,
            'is_published' => true,
        ]);

        // Challenge score = 80
        StudentTeacherChallengeSubmission::query()->create([
            'user_id' => $student->id,
            'document_id' => $doc->id,
            'score' => 80,
            'status' => 'completed',
        ]);

        // Quiz attempt score = 100
        StudentQuizAttempt::query()->create([
            'user_id' => $student->id,
            'document_id' => $doc->id,
            'score' => 100,
            'status' => 'completed',
        ]);

        // 1. Quiz view
        $quizResponse = $this->actingAs($teacher)->get(route('teacher.reports', [
            'class_id' => $classroom->id,
            'type' => 'quiz',
        ]));
        $quizResponse->assertOk();
        $quizResponse->assertSee('100');

        // 2. Combined view (80 + 100) / 2 = 90
        $combinedResponse = $this->actingAs($teacher)->get(route('teacher.reports', [
            'class_id' => $classroom->id,
            'type' => 'combined',
        ]));
        $combinedResponse->assertOk();
        $combinedResponse->assertSee('90');
    }

    public function test_teacher_can_export_report_to_excel_xls(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'name' => 'Ibu Siti Aminah',
            'school_origin' => 'SMA Negeri 2 Bandung',
            'nip_nuptk' => '198705052011012003',
        ]);

        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'XII IPA 3',
            'target_grade' => 12,
            'join_code' => 'XIIIPA30',
        ]);

        $student = User::factory()->create([
            'name' => 'Dewi Sartika',
            'nisn' => '0098765432',
            'role' => 'student',
        ]);

        $classroom->students()->attach($student->id, ['joined_at' => now()]);

        $doc = Document::query()->create([
            'title' => 'Fisika Kuantum & Relativitas',
            'category' => 'Fisika',
            'target_grade' => 12,
            'body' => 'Materi teori relativitas Einstein.',
            'uploaded_by' => $teacher->id,
            'is_published' => true,
        ]);

        StudentTeacherChallengeSubmission::query()->create([
            'user_id' => $student->id,
            'document_id' => $doc->id,
            'score' => 95,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.reports.export_excel', [
            'class_id' => $classroom->id,
            'category' => 'Fisika',
            'type' => 'teacher_challenge',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $this->assertStringContainsString('attachment; filename="Rekap_Nilai_', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xls', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringContainsString('Ibu Siti Aminah', $content);
        $this->assertStringContainsString('SMA Negeri 2 Bandung', $content);
        $this->assertStringContainsString('Dewi Sartika', $content);
        $this->assertStringContainsString('0098765432', $content);
        $this->assertStringContainsString('mso-number-format', $content);
        $this->assertStringContainsString('Fisika Kuantum', $content);
        $this->assertStringContainsString('95', $content);
    }
}
