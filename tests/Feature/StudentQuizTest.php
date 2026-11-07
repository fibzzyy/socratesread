<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentQuizQuestion;
use App\Models\StudentQuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function createDocumentWith10QuizQuestions(): Document
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Dampak Perubahan Iklim Global',
            'category' => 'Geografi',
            'target_grade' => 'all',
            'body' => "Paragraf satu mengenai emisi karbon.\n\nParagraf dua mengenai kenaikan suhu bumi.",
            'excerpt' => 'Pelajari dinamika perubahan iklim dan dampaknya.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            DocumentQuizQuestion::query()->create([
                'document_id' => $document->id,
                'sequence' => $i,
                'question' => "Pertanyaan Pilihan Ganda nomor {$i}?",
                'options' => [
                    ['key' => 'A', 'text' => "Pilihan A nomor {$i}"],
                    ['key' => 'B', 'text' => "Pilihan B nomor {$i}"],
                    ['key' => 'C', 'text' => "Pilihan C nomor {$i}"],
                    ['key' => 'D', 'text' => "Pilihan D nomor {$i}"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Penjelasan pembahasan soal nomor {$i}.",
            ]);
        }

        return $document;
    }

    public function test_student_can_open_timed_quiz_page(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $document = $this->createDocumentWith10QuizQuestions();

        $response = $this->actingAs($student)->get(route('quiz.timed', ['document' => $document->id]));

        $response->assertOk();
        $response->assertViewHas('mode', 'timed_quiz');
        $response->assertViewHas('isLocked', false);
        $response->assertSee('Kuis Timer Pilihan Ganda');
        $response->assertSee('data-timer-seconds', false);
        $response->assertSee('SOAL 1 DARI 10');

        $this->assertDatabaseHas('student_quiz_attempts', [
            'user_id' => $student->id,
            'document_id' => $document->id,
            'mode' => 'timed_quiz',
            'status' => 'in_progress',
        ]);
    }

    public function test_student_can_submit_quiz_and_receive_instant_score(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $document = $this->createDocumentWith10QuizQuestions();
        $questions = $document->quizQuestions;

        $attempt = StudentQuizAttempt::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'mode' => 'timed_quiz',
            'status' => 'in_progress',
            'total_questions' => 10,
            'started_at' => now(),
        ]);

        // Student answers 8 correctly (A) and 2 wrong (B)
        $answers = [];
        foreach ($questions as $idx => $q) {
            $answers[$q->id] = ($idx < 8) ? 'A' : 'B';
        }

        $response = $this->actingAs($student)->postJson(route('quiz.submit'), [
            'attempt_id' => $attempt->id,
            'answers' => $answers,
            'time_spent_seconds' => 320,
        ]);

        $response->assertOk();
        $response->assertJson([
            'completed' => true,
            'score' => 80,
            'correct_count' => 8,
            'total_questions' => 10,
        ]);

        $attempt->refresh();
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(80, $attempt->score);
        $this->assertSame(8, $attempt->correct_count);
        $this->assertSame(320, $attempt->time_spent_seconds);
        $this->assertNotNull($attempt->completed_at);
        $this->assertTrue($attempt->isCompleted());
    }

    public function test_student_cannot_resubmit_completed_locked_quiz(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $document = $this->createDocumentWith10QuizQuestions();

        $attempt = StudentQuizAttempt::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'mode' => 'timed_quiz',
            'status' => 'completed',
            'score' => 90,
            'correct_count' => 9,
            'total_questions' => 10,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($student)->postJson(route('quiz.submit'), [
            'attempt_id' => $attempt->id,
            'answers' => ['1' => 'A'],
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Kuis ini sudah selesai dikumpulkan dan terkunci.',
        ]);
    }

    public function test_completed_quiz_renders_in_locked_review_mode_with_answers_and_explanations(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $document = $this->createDocumentWith10QuizQuestions();
        $firstQ = $document->quizQuestions->first();

        StudentQuizAttempt::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'mode' => 'timed_quiz',
            'status' => 'completed',
            'score' => 100,
            'correct_count' => 10,
            'total_questions' => 10,
            'answers' => [$firstQ->id => 'A'],
            'started_at' => now()->subMinutes(15),
            'completed_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($student)->get(route('quiz.timed', ['document' => $document->id]));

        $response->assertOk();
        $response->assertViewHas('isLocked', true);
        $response->assertSee('Hasil Pengerjaan Terkunci');
        $response->assertSee('Skor 100/100');
        $response->assertSee('Mode Review Kunci Jawaban');
        $response->assertSee($firstQ->explanation);
    }

    public function test_student_can_open_and_submit_practice_mode(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $document = $this->createDocumentWith10QuizQuestions();

        $response = $this->actingAs($student)->get(route('quiz.practice', ['document' => $document->id]));

        $response->assertOk();
        $response->assertViewHas('mode', 'practice');
        $response->assertSee('Latihan Pilihan Ganda');
    }
}
