<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeLockTest extends TestCase
{
    use RefreshDatabase;

    protected function createDocumentWithQuestions(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Etika Digital dan Literasi',
            'category' => 'Literasi',
            'target_grade' => 'all',
            'body' => "Paragraf satu mengenai etika digital.\n\nParagraf dua mengenai pentingnya verifikasi sumber.",
            'excerpt' => 'Pelajari pentingnya etika berliterasi di ruang digital.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $q1 = SocraticQuestion::query()->create([
            'document_id' => $document->id,
            'sequence' => 1,
            'skill' => 'Memahami gagasan',
            'prompt' => 'Apa gagasan pokok teks ini?',
            'context' => 'Konteks pertanyaan 1',
            'hint' => 'Petunjuk 1',
        ]);

        $q2 = SocraticQuestion::query()->create([
            'document_id' => $document->id,
            'sequence' => 2,
            'skill' => 'Menemukan bukti',
            'prompt' => 'Mana kalimat pendukungnya?',
            'context' => 'Konteks pertanyaan 2',
            'hint' => 'Petunjuk 2',
        ]);

        return [$document, [$q1, $q2]];
    }

    public function test_student_can_answer_and_complete_in_progress_challenge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$document, $questions] = $this->createDocumentWithQuestions();

        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'in_progress',
            'progress_percent' => 50,
            'started_at' => now(),
        ]);

        // Student answers Q1
        $res1 = $this->actingAs($student)->postJson(route('challenge.answer'), [
            'session_id' => $session->id,
            'question_id' => $questions[0]->id,
            'answer' => 'Gagasan pokoknya adalah pentingnya beretika di dunia digital.',
        ]);
        $res1->assertOk()->assertJson(['saved' => true]);

        // Student answers Q2
        $res2 = $this->actingAs($student)->postJson(route('challenge.answer'), [
            'session_id' => $session->id,
            'question_id' => $questions[1]->id,
            'answer' => 'Bukti terdapat pada paragraf kedua tentang verifikasi sumber.',
        ]);
        $res2->assertOk()->assertJson(['saved' => true]);

        // Complete challenge
        $finishRes = $this->actingAs($student)->postJson(route('challenge.complete'), [
            'session_id' => $session->id,
        ]);
        $finishRes->assertOk()->assertJson(['completed' => true]);

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame(100, $session->progress_percent);
        $this->assertNotNull($session->completed_at);
        $this->assertTrue($session->isCompleted());
    }

    public function test_student_cannot_save_answer_to_completed_locked_challenge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$document, $questions] = $this->createDocumentWithQuestions();

        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
        ]);

        StudentAnswer::query()->create([
            'reading_session_id' => $session->id,
            'socratic_question_id' => $questions[0]->id,
            'answer_text' => 'Jawaban asli yang sudah dikumpulkan.',
            'answered_at' => now()->subMinutes(35),
        ]);

        // Attempt to modify answer on locked session
        $response = $this->actingAs($student)->postJson(route('challenge.answer'), [
            'session_id' => $session->id,
            'question_id' => $questions[0]->id,
            'answer' => 'Jawaban baru yang ingin disusupkan.',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Tugas ini sudah selesai dikumpulkan dan terkunci. Jawaban tidak dapat diubah lagi.',
        ]);

        // Verify DB value was not altered
        $answer = StudentAnswer::query()->where('reading_session_id', $session->id)->first();
        $this->assertSame('Jawaban asli yang sudah dikumpulkan.', $answer->answer_text);
    }

    public function test_student_cannot_recomplete_already_completed_challenge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$document, $questions] = $this->createDocumentWithQuestions();

        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($student)->postJson(route('challenge.complete'), [
            'session_id' => $session->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Tugas ini sudah selesai dikumpulkan sebelumnya.',
        ]);
    }

    public function test_completed_challenge_view_renders_in_locked_review_mode(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$document, $questions] = $this->createDocumentWithQuestions();

        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(20),
        ]);

        StudentAnswer::query()->create([
            'reading_session_id' => $session->id,
            'socratic_question_id' => $questions[0]->id,
            'answer_text' => 'Pemikiran mendalam siswa.',
            'answered_at' => now()->subMinutes(25),
        ]);

        $response = $this->actingAs($student)->get(route('challenge', ['session' => $session->id]));

        $response->assertOk();
        $response->assertViewHas('isLocked', true);
        $response->assertSee('Tugas Telah Dikumpulkan');
        $response->assertSee('Terkunci');
        $response->assertSee('readonly disabled', false);
        $response->assertSee('Pemikiran mendalam siswa.');
    }

    public function test_revisiting_reading_page_for_completed_document_does_not_spawn_new_in_progress_session(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$document] = $this->createDocumentWithQuestions();

        ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        // Student opens reading page
        $response = $this->actingAs($student)->get(route('reading', ['document' => $document->id]));

        $response->assertOk();
        $response->assertSee('Tugas Selesai');
        $response->assertSee('Terkunci');
        $response->assertSee('Lihat hasil jawaban');

        // Total sessions for this student on this document must strictly remain 1
        $count = ReadingSession::query()
            ->where('user_id', $student->id)
            ->where('document_id', $document->id)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_student_cannot_earn_duplicate_xp_on_completed_reading(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$document] = $this->createDocumentWithQuestions();

        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->postJson(route('reading.progress'), [
            'session_id' => $session->id,
            'progress' => 100,
        ]);

        $response->assertOk();
        $response->assertJson([
            'progress' => 100,
            'xp_awarded' => 0,
            'is_locked' => true,
        ]);
    }
}
