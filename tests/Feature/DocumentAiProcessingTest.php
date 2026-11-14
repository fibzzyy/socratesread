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

    public function test_math_exponents_domain_and_rejection_of_berikut_stopword(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);
        $processor = new DocumentAiProcessor($mockModel);

        $mathMaterial = <<<'TEXT'
Definisi Eksponen
Jika a adalah bilangan real dan n adalah bilangan bulat positif, maka a^n menyatakan hasil kali bilangan a sebanyak n faktor dan didefinisikan sebagai perkalian berulang. Bilangan berpangkat atau disebut juga eksponen didefinisikan sebagai berikut.
Sifat-sifat eksponen: a^m . a^n = a^{m+n} dan a^m / a^n = a^{m-n}.
Fungsi eksponensial f(x) = 3^x menggambarkan penularan virus dan penyebaran hoaks di mana setiap orang menulari 3 orang pada fase berikutnya.
TEXT;

        $quiz = $processor->generateHeuristicQuizQuestions($mathMaterial, 'MTK', 'Matematika', 10);
        $this->assertCount(10, $quiz);

        foreach ($quiz as $q) {
            foreach ($q['options'] as $opt) {
                $this->assertNotSame('Berikut', trim($opt['text']));
                $this->assertNotSame('Sebagai berikut', trim($opt['text']));
            }
        }

        $challenges = $processor->generateHeuristicTeacherChallengeSuggestions($mathMaterial, 'MTK', 'Matematika');
        $this->assertCount(5, $challenges);
        $this->assertStringContainsString('a^n', $challenges[0]['question']);
        $this->assertStringContainsString('a^m', $challenges[1]['question']);
    }

    public function test_multibyte_ellipsis_and_safe_utf8_regex_compilation(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);
        $processor = new DocumentAiProcessor($mockModel);

        $mathWithEllipsis = <<<'TEXT'
Pola barisan bilangan: 4, 6, 8, 10, …
Jika diamati lebih teliti, suku berikutnya bertambah 2 secara teratur.
Rumus suku ke-n barisan aritmetika adalah U_n = a + (n - 1)b.
Pada baris ke-4: U_4 = 20 + (… × 4) = 36 kursi.
Jadi, pada baris ke-5 diperoleh jumlah kursi bertambah sesuai pola.
TEXT;

        $quiz = $processor->generateHeuristicQuizQuestions($mathWithEllipsis, 'Barisan', 'Matematika', 10);
        $this->assertCount(10, $quiz);

        $challenges = $processor->generateHeuristicTeacherChallengeSuggestions($mathWithEllipsis, 'Barisan', 'Matematika');
        $this->assertCount(5, $challenges);
    }

    public function test_domain_6_barisan_dan_deret_aritmetika_questions_and_challenges(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);
        $processor = new DocumentAiProcessor($mockModel);

        $seqText = <<<'TEXT'
Tujuan Pembelajaran
Setelah mempelajari bab ini, diharapkan kamu dapat menyelesaikan permasalahan kehidupan sehari-hari yang berkaitan dengan barisan dan deret.
Barisan dan deret sangat erat kaitannya dengan konsep pola bilangan yang telah kamu pelajari pada jenjang SMP.
Barisan bilangan dapat dinyatakan dalam bentuk U1, U2, U3, ..., Un.
Rumus umum menentukan suku ke-n pada barisan aritmetika adalah Un = a + (n - 1)b di mana a adalah suku pertama dan b adalah beda.
Beda pada barisan aritmetika dinyatakan dengan b = Un - U(n-1).
Eksplorasi Gedung Pertunjukan Seni: Baris ke-1 = 20, Baris ke-2 = 24, Baris ke-3 = 28, Baris ke-4 = 32, Baris ke-5 = 36 kursi.
Tabungan Rudi: U5 = 70.000 dengan rumus a + 4b = 70.000.
TEXT;

        $quiz = $processor->generateHeuristicQuizQuestions($seqText, 'p014', 'MATEMATIKA', 10);
        $this->assertCount(10, $quiz);

        foreach ($quiz as $q) {
            $this->assertNotEmpty($q['question']);
            $this->assertCount(4, $q['options']);
            foreach ($q['options'] as $opt) {
                $text = trim($opt['text']);
                $this->assertFalse(str_ends_with($text, ' yang'), "Option should not end with 'yang': {$text}");
                $this->assertFalse(str_starts_with($text, 'Setelah mempelajari bab'), "Option should not be learning objective boilerplate: {$text}");
                $this->assertFalse(str_contains($text, 'Peta Materi'), "Option should not contain Peta Materi: {$text}");
            }
        }

        $challenges = $processor->generateHeuristicTeacherChallengeSuggestions($seqText, 'p014', 'MATEMATIKA');
        $this->assertCount(5, $challenges);
        $this->assertStringContainsString('U_n', $challenges[1]['question']);
        $this->assertStringContainsString('gedung pertunjukan', $challenges[2]['question']);
        $this->assertStringContainsString('Rudi', $challenges[3]['question']);
    }

    public function test_domain_6_barisan_geometri_and_quiz_count_expansion_to_15(): void
    {
        $mockModel = Mockery::mock(LanguageModel::class);
        $mockModel->shouldReceive('isConfigured')->andReturn(false);
        $processor = new DocumentAiProcessor($mockModel);

        $geoText = <<<'TEXT'
2. Barisan Geometri
Lima anak berbagi kue dengan aturan berikut: rasio kue adalah setengah.
Rasio adalah hal yang penting dalam Barisan Geometri.
Rumus umum menentukan suku ke-n pada barisan geometri adalah Un = a * r^(n-1).
Contoh 3: Suku pertama adalah 4 dan suku ke-4 adalah 108. Rasio adalah 3.
Contoh 4: Seutas tali dibagi menjadi 5 bagian: potongan terpendek a = 16 cm dan terpanjang U5 = 81 cm.
Pembelahan bakteri: satu sel membelah menjadi 3 setiap 2 jam.
TEXT;

        $quiz = $processor->generateHeuristicQuizQuestions($geoText, 'PO14', 'MATEMATIKA', 15);
        $this->assertCount(15, $quiz);

        $geoKeywordsFound = 0;
        foreach ($quiz as $q) {
            $this->assertNotEmpty($q['question']);
            $this->assertCount(4, $q['options']);
            if (str_contains(mb_strtolower($q['question']), 'geometri') || str_contains(mb_strtolower($q['question']), 'rasio') || str_contains(mb_strtolower($q['question']), 'bakteri')) {
                $geoKeywordsFound++;
            }
        }
        $this->assertGreaterThanOrEqual(5, $geoKeywordsFound);
    }

    public function test_document_model_max_recommended_quiz_questions_and_ideal_recommendation(): void
    {
        $shortDoc = new \App\Models\Document(['body' => str_repeat('Teks materi ringkas barisan. ', 100)]); // ~3000 chars
        $this->assertEquals(15, $shortDoc->maxRecommendedQuizQuestions());
        $this->assertEquals(10, $shortDoc->idealRecommendedQuizQuestions());

        $medDoc = new \App\Models\Document(['body' => str_repeat('Teks materi sedang modul barisan aritmetika. ', 200)]); // ~8800 chars
        $this->assertEquals(25, $medDoc->maxRecommendedQuizQuestions());
        $this->assertEquals(15, $medDoc->idealRecommendedQuizQuestions());

        $longDoc = new \App\Models\Document(['body' => str_repeat('Teks materi panjang bab lengkap barisan dan deret buku matematika siswa. ', 300)]); // ~21000 chars
        $this->assertEquals(50, $longDoc->maxRecommendedQuizQuestions());
        $this->assertEquals(20, $longDoc->idealRecommendedQuizQuestions());
    }
}
