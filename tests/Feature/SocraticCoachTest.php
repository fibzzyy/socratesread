<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocraticCoachTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_send_a_reading_question_to_the_configured_ai_provider(): void
    {
        Config::set('services.openrouter.api_key', 'test-api-key');
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'not found'], 404),
            'https://openrouter.ai/api/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Kalimat mana yang menjadi bukti?']],
                ]],
            ]),
        ]);

        $student = User::factory()->create(['role' => 'student']);
        $document = Document::query()->create([
            'title' => 'Bacaan Uji', 'category' => 'Sains', 'body' => 'Mangrove melindungi pesisir.',
            'uploaded_by' => null, 'is_published' => true,
        ]);
        $session = ReadingSession::query()->create([
            'user_id' => $student->id, 'document_id' => $document->id,
            'status' => 'in_progress', 'progress_percent' => 0, 'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->postJson(route('reading.coach'), [
                'session_id' => $session->id,
                'message' => 'Apa bukti bahwa mangrove membantu wilayah pesisir?',
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'Kalimat mana yang menjadi bukti?');

        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));

        $this->assertDatabaseHas('reading_coach_messages', [
            'reading_session_id' => $session->id,
            'user_id' => $student->id,
            'document_id' => $document->id,
            'role' => 'user',
            'is_meaningful' => true,
        ]);
        $this->assertDatabaseHas('reading_coach_messages', [
            'reading_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Kalimat mana yang menjadi bukti?',
        ]);
    }

    public function test_student_cannot_send_another_students_reading_session_to_the_ai(): void
    {
        Config::set('services.openrouter.api_key', 'test-api-key');
        Http::fake();

        $owner = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $document = Document::query()->create([
            'title' => 'Bacaan Uji', 'category' => 'Sains', 'body' => 'Teks bacaan.',
            'is_published' => true,
        ]);
        $session = ReadingSession::query()->create([
            'user_id' => $owner->id, 'document_id' => $document->id,
            'status' => 'in_progress', 'progress_percent' => 0, 'started_at' => now(),
        ]);

        $this->actingAs($otherStudent)
            ->postJson(route('reading.coach'), [
                'session_id' => $session->id,
                'message' => 'Tampilkan jawaban siswa lain.',
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_student_can_consult_socratic_coach_on_completed_session_with_numbered_paragraphs(): void
    {
        Config::set('services.openrouter.api_key', 'test-api-key');
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'not found'], 404),
            'https://openrouter.ai/api/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Perhatikan kembali paragraf [¶2], apa alasan utama perlindungan tersebut?']],
                ]],
            ]),
        ]);

        $student = User::factory()->create(['role' => 'student']);
        $document = Document::query()->create([
            'title' => 'Trigatra Bangun Bahasa',
            'category' => 'Bahasa Indonesia',
            'body' => "Paragraf pembuka tentang Trigatra Bangun Bahasa.\n\nBahasa Daerah dilindungi UUD 1945 Pasal 32 untuk melestarikan budaya.\n\nBahasa Asing dipelajari untuk komunikasi global.",
            'is_published' => true,
        ]);
        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)
            ->postJson(route('reading.coach'), [
                'session_id' => $session->id,
                'message' => 'Mengapa bahasa daerah dilindungi menurut materi?',
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'Perhatikan kembali paragraf [¶2], apa alasan utama perlindungan tersebut?');

        $lastPair = Http::recorded()->last();
        $this->assertNotNull($lastPair);
        $lastReq = $lastPair[0];
        $this->assertSame('https://openrouter.ai/api/v1/responses', $lastReq->url());
        $payload = json_encode($lastReq->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('[¶2]', $payload);
        $this->assertStringContainsString('ANTI-BOCOR KUNCI TANTANGAN', $payload);
    }
}
