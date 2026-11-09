<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\AI\Contracts\LanguageModel;
use App\Services\AI\DocumentAiProcessor;
use App\Services\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class DocumentAiProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_ai_processor_cleans_noisy_text_and_generates_socratic_questions(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);

        $processor = new DocumentAiProcessor($mockModel);

        $rawText = "BAB 1: EKOSISTEM LAUT\n\nHalaman 1\n\nLaut menyediakan oksigen dan pangan bagi miliaran manusia.\n\nHal 2\n\nTerumbu karang menjadi rumah bagi ribuan biota laut.\n\nPage 3 of 10\n\nPencemaran plastik mengancam kelestarian biota ini.";

        $result = $processor->process($rawText, 'Ekosistem Laut', 'Biologi');

        $this->assertNotEmpty($result['cleaned_body']);
        $this->assertStringNotContainsString('Halaman 1', $result['cleaned_body']);
        $this->assertStringNotContainsString('Hal 2', $result['cleaned_body']);
        $this->assertStringNotContainsString('Page 3 of 10', $result['cleaned_body']);
        $this->assertStringContainsString('Laut menyediakan oksigen', $result['cleaned_body']);
        $this->assertNotEmpty($result['excerpt']);
        $this->assertCount(5, $result['questions']);
        $this->assertCount(10, $result['quiz_questions']);
        $this->assertSame('Pengertian & Konsep Utama', $result['questions'][0]['skill']);
        $this->assertSame('Fungsi & Peranan Utama', $result['questions'][1]['skill']);
        $this->assertSame('Karakteristik & Komponen Kunci', $result['questions'][2]['skill']);
        $this->assertSame('Cara Kerja & Mekanisme', $result['questions'][3]['skill']);
        $this->assertSame('Manfaat & Penerapan Praktis', $result['questions'][4]['skill']);
    }

    public function test_document_ai_processor_parses_configured_ai_response(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(true);
        $mockModel->shouldReceive('respond')->andReturn(json_encode([
            'cleaned_body' => "Paragraf satu hasil kurasi AI.\n\nParagraf dua hasil kurasi AI.",
            'excerpt' => 'Ini intisari menarik dari AI.',
            'questions' => [
                ['sequence' => 1, 'skill' => 'Memahami gagasan', 'prompt' => 'Apa ide utama?', 'context' => 'Konteks 1', 'hint' => 'Petunjuk 1'],
                ['sequence' => 2, 'skill' => 'Menemukan bukti', 'prompt' => 'Mana buktinya?', 'context' => 'Konteks 2', 'hint' => 'Petunjuk 2'],
                ['sequence' => 3, 'skill' => 'Menjelaskan alasan', 'prompt' => 'Mengapa penting?', 'context' => 'Konteks 3', 'hint' => 'Petunjuk 3'],
            ],
        ]));

        $processor = new DocumentAiProcessor($mockModel);
        $result = $processor->process('Teks mentah awal', 'Judul Bab', 'Fisika');

        $this->assertSame("Paragraf satu hasil kurasi AI.\n\nParagraf dua hasil kurasi AI.", $result['cleaned_body']);
        $this->assertSame('Ini intisari menarik dari AI.', $result['excerpt']);
        $this->assertCount(5, $result['questions']);
        $this->assertCount(10, $result['quiz_questions']);
        $this->assertSame('Apa ide utama?', $result['questions'][0]['prompt']);
    }

    public function test_teacher_uploading_pdf_creates_document_with_socratic_questions(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $mockExtractor = Mockery::mock(PdfTextExtractor::class);
        $mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'text' => "Pengantar Revolusi Industri.\n\nHalaman 1\n\nMesin uap mengubah sistem produksi global secara drastis.\n\nPerubahan ini memicu urbanisasi massal.",
                'pages' => 2,
            ]);
        $this->app->instance(PdfTextExtractor::class, $mockExtractor);

        $file = UploadedFile::fake()->create('materi-sejarah.pdf', 50, 'application/pdf');

        $response = $this->actingAs($teacher)
            ->post(route('teacher.documents.store'), [
                'title' => 'Revolusi Industri',
                'category' => 'Sejarah',
                'target_grade' => '11',
                'file' => $file,
            ]);

        $response->assertRedirect(route('teacher.documents'));
        $response->assertSessionHas('status');

        $document = Document::query()->where('title', 'Revolusi Industri')->first();
        $this->assertNotNull($document);
        $this->assertSame('Sejarah', $document->category);
        $this->assertSame('11', $document->target_grade);
        $this->assertStringNotContainsString('Halaman 1', $document->body);
        $this->assertCount(5, $document->questions);
        $this->assertCount(10, $document->quizQuestions);
        $this->assertSame(1, $document->questions[0]->sequence);
        $this->assertSame('Pengertian & Konsep Utama', $document->questions[0]->skill);
    }
}
