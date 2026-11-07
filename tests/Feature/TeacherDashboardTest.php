<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LearningActivity;
use App\Models\ReadingSession;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_dashboard_only_includes_students_who_read_the_teachers_documents(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['name' => 'Siswa Milik Guru']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $otherStudent = User::factory()->create(['name' => 'Siswa Guru Lain']);
        $teacherClass = TeacherClass::query()->create([
            'teacher_id' => $teacher->id, 'name' => 'Kelas Guru Ini', 'join_code' => 'GURU1234',
        ]);
        $otherClass = TeacherClass::query()->create([
            'teacher_id' => $otherTeacher->id, 'name' => 'Kelas Guru Lain', 'join_code' => 'LAIN1234',
        ]);
        $teacherClass->students()->attach($student->id, ['joined_at' => now()]);
        $otherClass->students()->attach($otherStudent->id, ['joined_at' => now()]);

        $document = Document::query()->create([
            'title' => 'Bacaan guru ini', 'category' => 'Sains', 'body' => 'Teks bacaan',
            'uploaded_by' => $teacher->id, 'is_published' => true,
        ]);
        $otherDocument = Document::query()->create([
            'title' => 'Bacaan guru lain', 'category' => 'Sains', 'body' => 'Teks lain',
            'uploaded_by' => $otherTeacher->id, 'is_published' => true,
        ]);
        $session = ReadingSession::query()->create([
            'user_id' => $student->id, 'document_id' => $document->id,
            'status' => 'in_progress', 'progress_percent' => 45, 'started_at' => now(),
        ]);
        ReadingSession::query()->create([
            'user_id' => $otherStudent->id, 'document_id' => $otherDocument->id,
            'status' => 'completed', 'progress_percent' => 100, 'started_at' => now(),
            'completed_at' => now(),
        ]);
        LearningActivity::query()->create([
            'user_id' => $student->id, 'reading_session_id' => $session->id,
            'document_id' => $document->id, 'event_type' => 'reading_completed',
            'xp_awarded' => 10, 'summary' => 'Menyelesaikan bacaan', 'activity_date' => now()->toDateString(),
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Siswa Milik Guru')
            ->assertDontSee('Siswa Guru Lain')
            ->assertSee('Bacaan Anda')
            ->assertSee('45%');
    }

    public function test_teacher_dashboard_shows_student_grade_badge(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Bambang Siswa',
            'entry_grade' => 11,
            'enrollment_year' => (int) date('Y'),
            'graduation_year' => (int) date('Y') + 1,
        ]);
        $teacherClass = TeacherClass::query()->create([
            'teacher_id' => $teacher->id, 'name' => 'Kelas Fisika', 'target_grade' => 11, 'join_code' => 'FISIKA11',
        ]);
        $teacherClass->students()->attach($student->id, ['joined_at' => now()]);

        $document = Document::query()->create([
            'title' => 'Materi Fisika', 'category' => 'Sains', 'target_grade' => '11', 'body' => 'Teks fisika',
            'uploaded_by' => $teacher->id, 'is_published' => true,
        ]);
        ReadingSession::query()->create([
            'user_id' => $student->id, 'document_id' => $document->id,
            'status' => 'in_progress', 'progress_percent' => 50, 'started_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Bambang Siswa')
            ->assertSee('Kelas 11');
    }

    public function test_teacher_can_create_classroom_with_target_grade(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->post(route('teacher.classrooms.store'), [
                'name' => 'Biologi 10-A',
                'target_grade' => 10,
            ])
            ->assertRedirect(route('teacher.classrooms'));

        $this->assertDatabaseHas('teacher_classes', [
            'teacher_id' => $teacher->id,
            'name' => 'Biologi 10-A',
            'target_grade' => 10,
        ]);
    }

    public function test_teacher_can_filter_documents_by_grade(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Document::query()->create([
            'title' => 'Fisika Dasar 10', 'category' => 'Sains', 'body' => 'Teks fisika',
            'uploaded_by' => $teacher->id, 'target_grade' => '10', 'is_published' => true,
        ]);
        Document::query()->create([
            'title' => 'Kimia Lanjutan 11', 'category' => 'Sains', 'body' => 'Teks kimia',
            'uploaded_by' => $teacher->id, 'target_grade' => '11', 'is_published' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.documents', ['grade' => '10']))
            ->assertOk()
            ->assertSee('Fisika Dasar 10')
            ->assertDontSee('Kimia Lanjutan 11');

        $this->actingAs($teacher)
            ->get(route('teacher.documents', ['grade' => '11']))
            ->assertOk()
            ->assertSee('Kimia Lanjutan 11')
            ->assertDontSee('Fisika Dasar 10');
    }

    public function test_teacher_can_archive_and_reactivate_classroom(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas Literasi X',
            'join_code' => 'LITERA01',
            'is_active' => true,
        ]);

        // Archive
        $this->actingAs($teacher)
            ->post(route('teacher.classrooms.toggle-status', $classroom))
            ->assertRedirect();

        $this->assertFalse($classroom->fresh()->is_active);

        // Reactivate
        $this->actingAs($teacher)
            ->post(route('teacher.classrooms.toggle-status', $classroom))
            ->assertRedirect();

        $this->assertTrue($classroom->fresh()->is_active);
    }

    public function test_teacher_cannot_toggle_status_of_other_teachers_classroom(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $otherTeacher->id,
            'name' => 'Kelas Guru Lain',
            'join_code' => 'LAIN1234',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.classrooms.toggle-status', $classroom))
            ->assertForbidden();
    }

    public function test_teacher_can_export_student_progress_csv(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['name' => 'Andi Siswa', 'role' => 'student']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas X-1',
            'join_code' => 'KLS12345',
            'is_active' => true,
        ]);
        $classroom->students()->attach($student->id, ['joined_at' => now()]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.export.progress'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_student_cannot_open_teacher_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('teacher.dashboard'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('teacher.export.progress'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('teacher.students.show', 1))
            ->assertForbidden();
    }

    public function test_teacher_dashboard_shows_socratic_skills_overview(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id, 'name' => 'Kelas Literasi', 'join_code' => 'SOCRATES',
        ]);
        $classroom->students()->attach($student->id, ['joined_at' => now()]);

        $document = Document::query()->create([
            'title' => 'Bacaan Berpikir', 'category' => 'Filosofi', 'body' => 'Teks bacaan',
            'uploaded_by' => $teacher->id, 'is_published' => true,
        ]);
        $question = \App\Models\SocraticQuestion::query()->create([
            'document_id' => $document->id,
            'sequence' => 1,
            'skill' => 'Evaluasi Argumen',
            'prompt' => 'Apa bukti pendukungnya?',
        ]);
        $session = ReadingSession::query()->create([
            'user_id' => $student->id, 'document_id' => $document->id,
            'status' => 'completed', 'progress_percent' => 100, 'started_at' => now(),
        ]);
        \App\Models\StudentAnswer::query()->create([
            'reading_session_id' => $session->id,
            'socratic_question_id' => $question->id,
            'answer_text' => 'Buktinya ada pada paragraf pertama.',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Evaluasi Argumen')
            ->assertSee('1 siswa berlatih');
    }

    public function test_teacher_can_fetch_student_detail_json(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['name' => 'Citra Siswa', 'role' => 'student']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id, 'name' => 'Kelas IPA 10', 'join_code' => 'IPA10001',
        ]);
        $classroom->students()->attach($student->id, ['joined_at' => now()]);

        $document = Document::query()->create([
            'title' => 'Ekosistem Laut', 'category' => 'Sains', 'body' => 'Teks ekosistem',
            'uploaded_by' => $teacher->id, 'is_published' => true,
        ]);
        ReadingSession::query()->create([
            'user_id' => $student->id, 'document_id' => $document->id,
            'status' => 'completed', 'progress_percent' => 100, 'started_at' => now(),
        ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.students.show', $student->id));

        $response->assertOk();
        $response->assertJsonPath('student.name', 'Citra Siswa');
        $response->assertJsonPath('student.classes.0', 'Kelas IPA 10');
        $response->assertJsonPath('sessions.0.title', 'Ekosistem Laut');
    }

    public function test_teacher_cannot_fetch_student_detail_of_unenrolled_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $unenrolledStudent = User::factory()->create(['name' => 'Siswa Luar', 'role' => 'student']);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', $unenrolledStudent->id))
            ->assertForbidden();
    }
}
