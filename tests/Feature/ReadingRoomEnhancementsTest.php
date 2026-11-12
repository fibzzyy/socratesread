<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\TeacherChallenge;
use App\Models\User;
use App\Services\ReadingMaterialFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingRoomEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatter_preserves_full_content_and_structures_lists_and_pages(): void
    {
        $formatter = new ReadingMaterialFormatter();
        $raw = "JUDUL MATERI\n\nSetelah mengikuti pembelajaran ini, siswa mampu:\n•Menjelaskan konsep pertama\n•Menguraikan konsep kedua\n\nTUJUAN PEMBELAJARAN\n\n1. Langkah pertama dalam konfigurasi.\n2. Langkah kedua dalam pengujian.\n\nSUMBER RUJUKAN\n•Moeliono (1985)";

        $formatted = $formatter->format($raw, 'Judul Materi');
        $this->assertStringContainsString('Tujuan Pembelajaran', mb_convert_case($formatted, MB_CASE_TITLE));
        $this->assertStringContainsString('Moeliono (1985)', $formatted);
        $this->assertStringContainsString('- Menjelaskan konsep pertama', $formatted);
        $this->assertStringContainsString('1. Langkah pertama', $formatted);

        $html = $formatter->toHtml($formatted);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<ol>', $html);

        $pages = $formatter->splitIntoPages($formatted, 120);
        $this->assertGreaterThanOrEqual(2, count($pages));
    }

    public function test_reading_room_locks_both_challenges_for_first_two_minutes_and_unlocks_after(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Trigatra Bangun Bahasa',
            'category' => 'Bahasa Indonesia',
            'target_grade' => 'all',
            'body' => "Paragraf pertama materi lengkap.\n\nParagraf kedua materi lengkap.",
            'excerpt' => 'Intisari materi.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);
        TeacherChallenge::query()->create([
            'document_id' => $document->id,
            'type' => 'essay',
            'sequence' => 1,
            'question' => 'Jelaskan makna prinsip utama!',
            'difficulty' => 'medium',
            'difficulty_weight' => 2,
        ]);

        // Sesi baru dimulai (0 detik) -> kedua tantangan terkunci
        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'in_progress',
            'progress_percent' => 0,
            'started_at' => now(),
        ]);

        // Daftar materi di Reading Room harus mengarahkan tombol Lanjutkan/Mulai ke halaman bacaan, bukan /challenge
        $indexRes = $this->actingAs($student)->get(route('reading', ['teacher' => $teacher->id, 'category' => 'Bahasa Indonesia']));
        $indexRes->assertOk();
        $indexRes->assertSee(route('reading', ['document' => $document->id]), false);

        // Akses POST /reading/{document}/start juga diarahkan ke halaman bacaan
        $startRes = $this->actingAs($student)->post(route('reading.start', ['document' => $document->id]));
        $startRes->assertRedirect(route('reading', ['document' => $document->id]));

        $lockedRes = $this->actingAs($student)->get(route('reading', ['document' => $document->id]));
        $lockedRes->assertOk();
        $lockedRes->assertSee('1. Challenge Socrates (Terbuka setelah 2m)');
        $lockedRes->assertSee('2. Tantangan Guru (Terbuka setelah 2m)');
        $lockedRes->assertSee('data-reading-mode-btn="book"', false);

        // Setelah >= 2 menit -> kedua tantangan terbuka
        $session->update(['started_at' => now()->subSeconds(125)]);
        $unlockedRes = $this->actingAs($student)->get(route('reading', ['document' => $document->id]));
        $unlockedRes->assertOk();
        $unlockedRes->assertSee('1. Challenge Socrates (PG');
        $unlockedRes->assertSee('2. Tantangan Guru (Analisis');

        // Menekan selesai membaca (100%) mengembalikan completed_at dan status terkunci
        $completeRes = $this->actingAs($student)->postJson(route('reading.progress'), [
            'session_id' => $session->id,
            'progress' => 100,
        ]);
        $completeRes->assertOk()
            ->assertJson(['progress' => 100, 'is_locked' => true])
            ->assertJsonStructure(['completed_at']);
    }
}
