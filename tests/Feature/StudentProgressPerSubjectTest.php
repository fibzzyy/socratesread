<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentAnswer;
use App\Models\TeacherClass;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProgressPerSubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_progress_page_renders_with_classroom_selection_and_detailed_class_view(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $classroom1 = TeacherClass::create([
            'teacher_id' => $teacher->id,
            'name' => 'Administrasi Jaringan',
            'join_code' => 'NET12345',
            'is_active' => true,
        ]);
        $student->classrooms()->attach($classroom1->id);

        $classroom2 = TeacherClass::create([
            'teacher_id' => $teacher->id,
            'name' => 'Sistem Operasi',
            'join_code' => 'OPS12345',
            'is_active' => true,
        ]);
        $student->classrooms()->attach($classroom2->id);

        $docNet = Document::create([
            'uploaded_by' => $teacher->id,
            'title' => 'Routing Statis Mikrotik',
            'category' => 'Administrasi Jaringan',
            'target_grade' => 'all',
            'body' => 'Pembahasan routing...',
            'excerpt' => 'Konfigurasi routing.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $q1 = SocraticQuestion::create([
            'document_id' => $docNet->id,
            'sequence' => 1,
            'skill' => 'Memahami gagasan',
            'prompt' => 'Apa tujuan routing statis?',
        ]);

        $gamification = app(GamificationService::class);

        // Siswa membaca dan menjawab pertanyaan di kelas Administrasi Jaringan
        $sessionNet = ReadingSession::create([
            'user_id' => $student->id,
            'document_id' => $docNet->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
        StudentAnswer::create([
            'reading_session_id' => $sessionNet->id,
            'socratic_question_id' => $q1->id,
            'answer_text' => 'Tujuan routing adalah menghubungkan antar jaringan.',
        ]);
        $gamification->awardOnce($sessionNet, 'reading_completed', 15, 'Selesai membaca Routing');

        // 1. Uji Halaman Utama Progress: Harus menampilkan kartu-kartu kelas
        $overviewResponse = $this->actingAs($student)->get(route('progress'));
        $overviewResponse->assertOk();
        $overviewResponse->assertSee('Administrasi Jaringan');
        $overviewResponse->assertSee('Sistem Operasi');
        $overviewResponse->assertSee('Buka Progress Kelas Ini');

        // 2. Uji Halaman Detail Kelas yang Dipilih: Harus menampilkan 4 blok khusus kelas itu
        $classResponse = $this->actingAs($student)->get(route('progress', ['classroom' => $classroom1->id]));
        $classResponse->assertOk();
        $classResponse->assertSee('Progress Kelas Administrasi Jaringan');
        $classResponse->assertSee('Semua Kelas');
        // PENGALAMAN MINGGUAN
        $classResponse->assertSee('PENGALAMAN MINGGUAN');
        $classResponse->assertSee('Aktivitas 7 hari terakhir');
        // KOMPETENSI MEMBACA (5 skill sokratik & progress proporsional)
        $classResponse->assertSee('KOMPETENSI MEMBACA');
        $classResponse->assertSee('Memahami gagasan');
        $classResponse->assertSee('1 dari 5 materi dilatih');
        // TARGET MINGGUAN
        $classResponse->assertSee('TARGET MINGGUAN');
        // JEJAK BELAJAR
        $classResponse->assertSee('JEJAK BELAJAR');
    }
}
