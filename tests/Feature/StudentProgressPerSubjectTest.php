<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LearningActivity;
use App\Models\ReadingCoachMessage;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentAnswer;
use App\Models\StudentQuizAttempt;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherChallenge;
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

        // Gunakan nama skill khusus topik (bukan string hardcoded) untuk memastikan kompetensi tetap terhitung
        $q1 = SocraticQuestion::create([
            'document_id' => $docNet->id,
            'sequence' => 1,
            'skill' => 'Konsep Routing Statis',
            'prompt' => 'Apa tujuan routing statis?',
        ]);

        $gamification = app(GamificationService::class);

        $sessionNet = ReadingSession::create([
            'user_id' => $student->id,
            'document_id' => $docNet->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
        StudentAnswer::create([
            'reading_session_id' => $sessionNet->id,
            'socratic_question_id' => $q1->id,
            'answer_text' => 'Tujuan routing adalah menghubungkan antar jaringan secara terstruktur.',
            'score' => 85,
        ]);
        $gamification->awardOnce($sessionNet, 'reading_completed', 15, 'Selesai membaca Routing');

        // Catat penyelesaian BOTH Challenge Socrates PG dan Tantangan Guru pada materi yang sama
        StudentQuizAttempt::create([
            'user_id' => $student->id,
            'document_id' => $docNet->id,
            'mode' => 'timed_quiz',
            'status' => 'completed',
            'score' => 80,
            'xp_awarded' => 25,
            'correct_count' => 8,
            'total_questions' => 10,
            'completed_at' => now(),
        ]);
        LearningActivity::create([
            'user_id' => $student->id,
            'reading_session_id' => $sessionNet->id,
            'document_id' => $docNet->id,
            'event_type' => 'quiz_timed_quiz',
            'xp_awarded' => 25,
            'summary' => 'Menyelesaikan Kuis Timer Pilihan Ganda (Skor 80/100)',
            'activity_date' => now()->toDateString(),
        ]);

        TeacherChallenge::create([
            'document_id' => $docNet->id,
            'type' => 'essay',
            'sequence' => 1,
            'question' => 'Mengapa routing statis aman untuk jaringan kecil?',
            'difficulty' => 'medium',
            'difficulty_weight' => 2,
        ]);
        StudentTeacherChallengeSubmission::create([
            'user_id' => $student->id,
            'document_id' => $docNet->id,
            'score' => 90,
            'xp_awarded' => 30,
            'status' => 'completed',
            'completed_at' => now(),
            'answers' => [
                1 => [
                    'type' => 'essay',
                    'difficulty' => 'medium',
                    'quality_percent' => 90,
                    'has_evidence' => true,
                    'is_copied' => false,
                ],
            ],
        ]);
        LearningActivity::create([
            'user_id' => $student->id,
            'reading_session_id' => $sessionNet->id,
            'document_id' => $docNet->id,
            'event_type' => 'teacher_challenge',
            'xp_awarded' => 30,
            'summary' => 'Menyelesaikan Tantangan Guru: Routing Statis Mikrotik (Skor 90/100)',
            'activity_date' => now()->toDateString(),
        ]);

        ReadingCoachMessage::create([
            'reading_session_id' => $sessionNet->id,
            'user_id' => $student->id,
            'document_id' => $docNet->id,
            'role' => 'user',
            'content' => 'Mengapa gateway diperlukan pada rute statis?',
            'is_meaningful' => true,
        ]);

        // 1. Uji Halaman Utama Progress: Kartu kelas + Mata Pelajaran pada Jejak Belajar Terakhir
        $overviewResponse = $this->actingAs($student)->get(route('progress'));
        $overviewResponse->assertOk();
        $overviewResponse->assertSee('Administrasi Jaringan');
        $overviewResponse->assertSee('Sistem Operasi');
        $overviewResponse->assertSee('Buka Progress Kelas Ini');
        $overviewResponse->assertSee('Administrasi Jaringan · Routing Statis Mikrotik');
        $this->assertSame(2, $overviewResponse->viewData('completedChallenges'));

        // 2. Uji Halaman Detail Kelas yang Dipilih
        $classResponse = $this->actingAs($student)->get(route('progress', ['classroom' => $classroom1->id]));
        $classResponse->assertOk();
        $classResponse->assertSee('Progress Kelas Administrasi Jaringan');
        $classResponse->assertSee('Semua Kelas');
        $classResponse->assertSee('KOMPETENSI MEMBACA');
        $classResponse->assertSee('Memahami gagasan');
        $classResponse->assertSee('1 dari 1 materi');
        $classResponse->assertSee('Administrasi Jaringan · Routing Statis Mikrotik');

        $skills = $classResponse->viewData('skillsCompetency');
        $this->assertCount(5, $skills);
        foreach ($skills as $s) {
            $this->assertGreaterThan(0, $s->percent, "Skill {$s->skill} should be > 0% when activities exist");
        }
    }
}
