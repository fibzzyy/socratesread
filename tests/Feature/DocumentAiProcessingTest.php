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

    public function test_heuristic_quiz_questions_focus_on_material_content_with_unique_options(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);

        $processor = new DocumentAiProcessor($mockModel);

        $body = <<<TEXT
LEMBAR KERJA MAHASISWA IDENTIFIKASI KALIMAT
1. "Target reduksi redundansi data sebesar 30% yang dicanangkan pada awal semester ini akhirnya tertuang dalam dokumentasi arsitektur basis data yang baru."
2. "Spesifikasi kebutuhan perangkat lunak ini memerlukan integrasi modul enkripsi asimetris dengan algoritma RSA 2048-bit untuk menjamin kerahasiaan data pengguna."
3. "Penerapan metodologi Agile Scrum dalam siklus pengembangan sistem informasi akuntansi ini dirancang guna meminimalkan risiko keterlambatan rilis produk."
4. "Berdasarkan rencana strategis triwulan pertama, alokasi kapasitas penyimpanan komputasi awan yang semula dipatok sebesar 5 Terabita kini menyisakan ruang 1,2 Terabita."
5. "Pengujian beban (load testing) pada sistem gateway pembayaran dengan simulasi 10.000 transaksi per detik menempatkan utilisasi CPU pada batas aman 70%."
TEXT;

        $questions = $processor->generateHeuristicQuizQuestions($body, 'BAHAN MATERI', 'SISTEM OPERASI', 10);

        $this->assertCount(10, $questions);
        foreach ($questions as $q) {
            $this->assertStringNotContainsString('manakah pernyataan yang paling tepat', mb_strtolower($q['question']));
            $this->assertStringNotContainsString('LEMBAR KERJA MAHASISWA', $q['question']);

            $optionTexts = array_map(fn ($o) => mb_strtolower(trim($o['text'])), $q['options']);
            $this->assertCount(4, array_unique($optionTexts), 'All 4 options must be strictly unique.');
        }
    }

    public function test_whole_word_keyword_search_and_bloom_c1_to_c5_with_qa_auto_repair_and_option_filtering(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);
        $processor = new DocumentAiProcessor($mockModel);

        // 1. Pencarian kata utuh: kata pendek "isi" tidak boleh cocok di dalam "posisi" atau "tradisi"
        $this->assertFalse($processor->containsWholeWordTerm('Kedudukan bahasa menjaga posisi budaya dan tradisi.', 'isi'));
        $this->assertTrue($processor->containsWholeWordTerm('Ringkasan mempertahankan urutan isi teks asli.', 'isi'));

        $similarity = app(\App\Services\Evaluation\Layers\SemanticSimilarityEvaluator::class);
        $mapFromPosisi = $similarity->expandConceptMap($similarity->extractKeywords('Posisi strategis budaya daerah'), 'Posisi strategis budaya daerah');
        $this->assertFalse($similarity->tokenMatchesConceptMap('isi', $mapFromPosisi, 'Posisi strategis budaya daerah'));

        // 2. Templat kata kerja Bloom C1–C5 termasuk Evaluasi, soal tidak menyalin paragraf utuh, & pengecoh tersaring
        $tbbMaterial = <<<'TEXT'
Trigatra Bangun Bahasa adalah strategi kebahasaan Indonesia yang memadukan peran bahasa Indonesia, bahasa daerah, dan bahasa asing dalam pembangunan bangsa.

Bahasa Indonesia memiliki kedudukan sebagai bahasa nasional dan bahasa negara sesuai UUD 1945 Pasal 36 sebagai alat pemersatu bangsa dan komunikasi resmi.

Bahasa Daerah merupakan bagian dari kebudayaan nasional yang dilindungi UUD 1945 Pasal 32 untuk melestarikan tradisi serta kearifan lokal masyarakat.

Prinsip keseimbangan kebahasaan menuntut setiap warga mengutamakan bahasa Indonesia, melestarikan bahasa daerah, dan menguasai setidaknya satu bahasa asing di era digital.
TEXT;

        $heuristicEssays = $processor->generateHeuristicQuestions($tbbMaterial, 'Trigatra Bangun Bahasa', 'Bahasa Indonesia');
        $this->assertCount(5, $heuristicEssays);
        $this->assertSame('C5', $heuristicEssays[4]['bloom_level']);
        $this->assertStringContainsStringIgnoringCase('evaluasi', $heuristicEssays[4]['prompt']);

        $quiz = $processor->generateHeuristicQuizQuestions($tbbMaterial, 'Trigatra Bangun Bahasa', 'Bahasa Indonesia', 10);
        $this->assertCount(10, $quiz);
        $hasEvaluasiQuiz = false;
        foreach ($quiz as $item) {
            if (preg_match('/\bevaluasi\b/iu', $item['question'])) {
                $hasEvaluasiQuiz = true;
            }
            $this->assertLessThanOrEqual(135, mb_strlen($item['question']), 'Question stem must stay concise without copying full paragraphs.');
            foreach ($item['options'] as $opt) {
                $this->assertNotEmpty(trim($opt['text']));
                $this->assertStringNotContainsString('Balai Pustaka', $opt['text']);
                $this->assertStringNotContainsString('Menonaktifkan seluruh mekanisme verifikasi', $opt['text']);
            }
        }
        $this->assertTrue($hasEvaluasiQuiz, 'Generated quiz must include Bloom C5 Mengevaluasi questions.');

        // 3. Penyaringan opsi kuis yang tidak masuk akal & anti-bocor
        $this->assertFalse($processor->isPlausibleQuizOption('Balai Pustaka (2017)', 'Bahasa pemersatu bangsa'));
        $this->assertFalse($processor->isPlausibleQuizOption('Alwi, H., &', 'Bahasa pemersatu bangsa'));
        $this->assertFalse($processor->isPlausibleQuizOption('& ilmu pengetahuan', 'Bahasa pemersatu bangsa'));
        $this->assertFalse($processor->isPlausibleQuizOption('dan kebudayaan', 'Bahasa pemersatu bangsa'));
        $this->assertFalse($processor->isPlausibleQuizOption('Pernyataan penting dalam', 'Bahasa pemersatu bangsa'));
        $this->assertFalse($processor->isPlausibleQuizOption('Kalimat panjang tentang teknologi jaringan informasi', 'Pasal 36'));
        $this->assertTrue($processor->isPlausibleQuizOption('Pasal 32', 'Pasal 36'));
        $this->assertFalse($processor->isPlausibleQuizOption('Sarana komunikasi resmi', 'Sarana komunikasi resmi', 'Analisis peran sarana komunikasi resmi menurut materi!'));
        $this->assertTrue($processor->isQuestionLeakingAnswer('Analisis fungsi bahasa sebagai sarana komunikasi resmi!', 'sarana komunikasi resmi'));
        $this->assertFalse($processor->isQuestionLeakingAnswer('Analisis fungsi utama bahasa Indonesia menurut materi!', 'Sebagai sarana komunikasi resmi'));

        // 4. Pemeriksaan otomatis Q<->A memperbaiki kunci jawaban yang salah arah / label mentah dari AI
        $repaired = $processor->verifyAndRepairEssayPair(
            'Mengapa Bahasa Daerah perlu dilindungi menurut aturan UUD 1945 pada materi?',
            'Kedudukan: bahasa asing.',
            '',
            $tbbMaterial,
            'Trigatra Bangun Bahasa'
        );
        $this->assertTrue($repaired['is_valid']);
        $this->assertStringContainsString('Pasal 32', $repaired['answer_key'] . ' ' . $repaired['reference_excerpt']);
    }
}
