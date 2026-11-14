<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Models\User;
use App\Services\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class DocumentUploadScopeAndToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_subtopic_boundary_cuts_text_cleanly_before_next_heading(): void
    {
        $extractor = new PdfTextExtractor();

        $text = "1. Pendahuluan\nPengantar materi.\n\n2.4 Kaidah Kebahasaan\nBahasa baku sangat krusial dalam karya ilmiah.\nKata denotatif harus diutamakan.\n\n2.5 Cara Penulisan Kutipan\nKutipan langsung dan tidak langsung dibahas minggu depan.";

        $cut = $extractor->cutTextAtSubtopicBoundary($text, '2.4 Kaidah Kebahasaan');

        $this->assertStringContainsString('2.4 Kaidah Kebahasaan', $cut);
        $this->assertStringContainsString('Kata denotatif harus diutamakan', $cut);
        $this->assertStringNotContainsString('2.5 Cara Penulisan Kutipan', $cut);
        $this->assertStringNotContainsString('minggu depan', $cut);
    }

    public function test_teacher_can_upload_with_page_range_and_last_subtopic_heading(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $mockExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockExtractor->shouldReceive('extract')
            ->once()
            ->withArgs(function ($file, $start, $end, $heading) {
                return $start === 5 && $end === 20 && $heading === '2.4 Kaidah Kebahasaan';
            })
            ->andReturn([
                'text' => "BAB 2 KARYA TULIS ILMIAH\n\n2.4 Kaidah Kebahasaan\nKarya ilmiah memakai ragam bahasa baku dan struktur logis.",
                'pages' => 50,
                'extracted_pages' => 16,
            ]);
        $this->app->instance(PdfTextExtractor::class, $mockExtractor);

        $file = UploadedFile::fake()->create('buku-paket-tebal.pdf', 100, 'application/pdf');

        $response = $this->actingAs($teacher)
            ->post(route('teacher.documents.store'), [
                'title' => 'Bahasa Indonesia Bab 2',
                'category' => 'Bahasa Indonesia',
                'target_grade' => '11',
                'page_scope' => 'range',
                'page_start' => 5,
                'page_end' => 20,
                'last_subtopic_heading' => '2.4 Kaidah Kebahasaan',
                'enable_teacher_challenges' => '1',
                'file' => $file,
            ]);

        $response->assertRedirect(route('teacher.documents'));
        $response->assertSessionHas('status');

        $document = Document::query()->where('title', 'Bahasa Indonesia Bab 2')->first();
        $this->assertNotNull($document);
        $this->assertCount(10, $document->quizQuestions);
        $this->assertGreaterThanOrEqual(1, $document->teacherChallenges()->count());
    }

    public function test_teacher_can_disable_teacher_challenges_toggle(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $mockExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'text' => "Materi Konsep Dasar Algoritma.\n\nAlgoritma adalah urutan langkah terstruktur untuk memecahkan persoalan komputasi.",
                'pages' => 5,
                'extracted_pages' => 5,
            ]);
        $this->app->instance(PdfTextExtractor::class, $mockExtractor);

        $file = UploadedFile::fake()->create('algoritma.pdf', 50, 'application/pdf');

        // Checkbox enable_teacher_challenges TIDAK dikirim (karena dicentang mati oleh guru)
        $response = $this->actingAs($teacher)
            ->post(route('teacher.documents.store'), [
                'title' => 'Algoritma Komputasi',
                'category' => 'Informatika',
                'target_grade' => '10',
                'page_scope' => 'all',
                'has_teacher_challenges_toggle' => '1',
                'file' => $file,
            ]);

        $response->assertRedirect(route('teacher.documents'));
        $this->assertStringContainsString('Tantangan Guru dinonaktifkan', session('status'));

        $document = Document::query()->where('title', 'Algoritma Komputasi')->first();
        $this->assertNotNull($document);
        // Kuis Socrates 10 Soal tetap dibuat
        $this->assertCount(10, $document->quizQuestions);
        // Tantangan Guru 0 buah karena dinonaktifkan guru
        $this->assertSame(0, $document->teacherChallenges()->count());
    }

    public function test_clean_shadow_and_duplicates_handles_malformed_utf8_and_math_symbols(): void
    {
        $extractor = new PdfTextExtractor();
        $corruptedUtf8 = "Rumus eksponen: 2^3 \x80\xFF \xE2\x88\x9A(x) = y^2";
        $cleaned = $extractor->cleanShadowAndDuplicates($corruptedUtf8);

        $this->assertNotEmpty($cleaned);
        $this->assertTrue(mb_check_encoding($cleaned, 'UTF-8'));
    }
}
