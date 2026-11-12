<?php

namespace Tests\Feature;

use App\Http\Controllers\Student\StudentTeacherChallengeController;
use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherChallenge;
use App\Models\User;
use App\Services\AI\DocumentAiProcessor;
use App\Services\Evaluation\AnswerEvaluationPipeline;
use App\Services\Evaluation\AnswerHighlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroundedChallengeAndParaphraseTest extends TestCase
{
    use RefreshDatabase;

    private string $sampleMaterial = <<<'MD'
# SUB DOMAIN VIRTUAL HOST

Logicodix 2026 · Subtema: Smart & Fun Education · @ikii

Selain domain utama semisal amjapoltesa.net, masih bisa kita tambahkan lagi Sub-Domain dari domain utama tersebut. Sehingga kita lebih menghemat Ip Address dan juga domain.

Contoh subdomain adalah, mail.yahoo.com dan mail.google.com.

Lalu bagaimana anda dapat mengisi atau mengganti dari setiap sub-domain? Anda cukup membuat virtualhost saja lalu anda aktifkan melalui apache.

```bash
#mkdir portal
#nano /var/www/portal/index.html
```

Simpan dan keluar dengan menekan ctrl+x tekan y tekan enter, kemudian anda berpindah directori untuk memulai konfigurasi virtualhost dengan perintah seperti dibawah.

Anda berpindah directory ke sites-availeble, untuk mempermudah konfigurasi silahkan anda salin file default ke nama sub-domain seperti portal.

Setelah itu anda coba buka melalui browser client jika tidak bisa terbuka atau masih sama coba masukan perintah "service apache2 restart" atau tekan f5 dibrowser anda atau mungkin lagi periksa konfigurasi anda.
MD;

    public function test_evidence_first_suggestions_align_question_key_and_reference(): void
    {
        $processor = app(DocumentAiProcessor::class);
        $suggestions = $processor->generateTeacherChallengeSuggestions(
            $this->sampleMaterial,
            'SUB DOMAIN VIRTUAL HOST',
            'ADMINISTRASI JARINGAN'
        );

        $this->assertCount(5, $suggestions);

        // Soal #1 tentang Sub-Domain harus memiliki kunci & rujukan yang membahas Sub-Domain (bukan #mkdir portal atau header Logicodix)
        $q1 = $suggestions[0];
        $this->assertStringContainsStringIgnoringCase('sub-domain', $q1['question']);
        $this->assertStringContainsStringIgnoringCase('sub-domain', $q1['rubric']);
        $this->assertStringContainsStringIgnoringCase('hemat', $q1['rubric']);
        $this->assertStringNotContainsString('#mkdir', $q1['rubric']);
        $this->assertStringNotContainsString('Logicodix', $q1['rubric']);
        $this->assertNotEmpty($q1['reference_excerpt']);
        $this->assertStringContainsStringIgnoringCase('sub-domain', $q1['reference_excerpt']);

        // Tidak boleh ada soal yang memakai header acara @ikii/Logicodix, contoh 7 kata, atau kalimat tutorial mentah "gambar dibawah"
        foreach ($suggestions as $item) {
            $this->assertNotEmpty($item['reference_excerpt']);
            $this->assertStringNotContainsString('Logicodix', $item['rubric']);
            $this->assertStringNotContainsString('@ikii', $item['rubric']);
            $this->assertStringNotContainsString('mail.yahoo.com', $item['rubric']);
            $this->assertStringNotContainsStringIgnoringCase('gambar dibawah', $item['rubric']);
            $this->assertStringNotContainsStringIgnoringCase('silahkan anda', $item['rubric']);
        }
    }

    public function test_resolve_model_answer_preserves_rich_rubric_and_reference_excerpt(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::create([
            'title' => 'SUB DOMAIN VIRTUAL HOST',
            'category' => 'ADMINISTRASI JARINGAN',
            'target_grade' => '10',
            'excerpt' => 'Penggunaan sub-domain dan virtualhost Apache.',
            'body' => $this->sampleMaterial,
            'uploaded_by' => $teacher->id,
            'estimated_minutes' => 3,
            'is_published' => true,
        ]);

        $rubric = 'Selain domain utama, kita dapat menambahkan sub-domain dari domain tersebut sehingga lebih menghemat IP Address dan domain.';
        $reference = 'Selain domain utama semisal amjapoltesa.net, masih bisa kita tambahkan lagi Sub-Domain dari domain utama tersebut.';

        $challenge = TeacherChallenge::create([
            'document_id' => $document->id,
            'sequence' => 1,
            'type' => 'essay',
            'question' => 'Apa fungsi penggunaan Sub-Domain menurut materi?',
            'context' => 'Jelaskan alasan penghematannya.',
            'rubric' => $rubric,
            'reference_excerpt' => $reference,
            'difficulty' => 'easy',
            'difficulty_weight' => 1,
        ]);

        $resolved = app(StudentTeacherChallengeController::class)->resolveModelAnswer($challenge, $document);

        $this->assertSame($rubric, $resolved['model_answer']);
        $this->assertSame($reference, $resolved['reference_excerpt']);
        $this->assertStringNotContainsString('Penjelasan dari bacaan:', $resolved['model_answer']);
    }

    public function test_paraphrase_evaluation_scores_high_while_off_topic_scores_low(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::create([
            'title' => 'SUB DOMAIN VIRTUAL HOST',
            'category' => 'ADMINISTRASI JARINGAN',
            'target_grade' => '10',
            'excerpt' => 'Subdomain dan VirtualHost pada Apache.',
            'body' => $this->sampleMaterial,
            'uploaded_by' => $teacher->id,
            'estimated_minutes' => 3,
            'is_published' => true,
        ]);

        $question = new SocraticQuestion([
            'document_id' => $document->id,
            'sequence' => 1,
            'skill' => 'Analisis Esai Guru',
            'prompt' => 'Apa fungsi penggunaan Sub-Domain sebagaimana dijelaskan pada awal materi?',
            'context' => 'Fokuskan pada alasan efisiensi domain dan IP.',
            'hint' => 'Selain domain utama semisal amjapoltesa.net, masih bisa kita tambahkan lagi Sub-Domain dari domain utama tersebut sehingga kita lebih menghemat IP Address dan juga domain.',
        ]);

        $pipeline = app(AnswerEvaluationPipeline::class);

        $paraphrase = $pipeline->evaluate(
            $document,
            $question,
            'Penambahan sub-domain pada domain utama berguna untuk membuat layanan baru tanpa harus membeli domain lain sehingga pemakaian alamat IP menjadi lebih hemat.'
        );

        $offTopic = $pipeline->evaluate(
            $document,
            $question,
            'Setiap pagi hari murid belajar membaca buku sastra klasik di perpustakaan sekolah agar wawasan semakin luas.'
        );

        $this->assertTrue($paraphrase->passed);
        $this->assertGreaterThanOrEqual(70, $paraphrase->score);
        $this->assertFalse($offTopic->passed);
        $this->assertLessThan(40, $offTopic->score);
    }

    public function test_teacher_challenge_paraphrase_submission_displays_accurate_points_and_review(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $document = Document::create([
            'title' => 'SUB DOMAIN VIRTUAL HOST',
            'category' => 'ADMINISTRASI JARINGAN',
            'target_grade' => '10',
            'excerpt' => 'Penggunaan sub-domain dan virtualhost.',
            'body' => $this->sampleMaterial,
            'uploaded_by' => $teacher->id,
            'estimated_minutes' => 3,
            'is_published' => true,
        ]);

        $q1 = TeacherChallenge::create([
            'document_id' => $document->id,
            'sequence' => 1,
            'type' => 'essay',
            'question' => 'Apa manfaat utama menambahkan Sub-Domain pada domain utama?',
            'context' => 'Jelaskan efisiensi sumber daya jaringan.',
            'rubric' => 'Penambahan Sub-Domain pada domain utama berfungsi untuk menghemat penggunaan IP Address dan domain utama.',
            'reference_excerpt' => 'Selain domain utama semisal amjapoltesa.net, masih bisa kita tambahkan lagi Sub-Domain dari domain utama tersebut. Sehingga kita lebih menghemat Ip Address dan juga domain.',
            'difficulty' => 'easy',
            'difficulty_weight' => 1,
        ]);

        $q2 = TeacherChallenge::create([
            'document_id' => $document->id,
            'sequence' => 2,
            'type' => 'essay',
            'question' => 'Langkah troubleshooting apa yang dilakukan jika sub-domain tidak terbuka di browser?',
            'context' => 'Sebutkan perintah restart layanan web server.',
            'rubric' => 'Jalankan perintah service apache2 restart, tekan F5 di browser client, dan periksa kembali konfigurasi virtualhost.',
            'reference_excerpt' => 'coba masukan perintah "service apache2 restart" atau tekan f5 dibrowser anda atau mungkin lagi periksa konfigurasi anda.',
            'difficulty' => 'medium',
            'difficulty_weight' => 2,
        ]);

        ReadingSession::create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
            'progress_percent' => 100,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->post(route('student.teacher-challenges.submit', $document), [
            'answers' => [
                $q1->id => 'Dengan menambahkan sub-domain dari domain utama yang ada, pengelolaan layanan lebih efisien sehingga kita bisa menghemat alamat IP dan penggunaan domain.',
                $q2->id => 'hai',
            ],
        ]);

        $response->assertRedirect(route('student.teacher-challenges.show', $document));

        $submission = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $student->id)
            ->where('document_id', $document->id)
            ->firstOrFail();

        $this->assertGreaterThan(0, (float) ($submission->answers[$q1->id]['points_earned'] ?? 0));
        $this->assertNotEmpty($submission->answers[$q1->id]['matched_spans'] ?? []);
        $this->assertEquals(0.0, (float) ($submission->answers[$q2->id]['points_earned'] ?? -1));

        $reviewPage = $this->actingAs($student)->get(route('student.teacher-challenges.show', $document));
        $reviewPage->assertOk();
        $reviewPage->assertSee('<strong class="font-bold text-slate-950">', false);
        $reviewPage->assertSee('Lihat Kunci Jawaban & Rujukan Teks', false);
        $reviewPage->assertSee('Kunci Jawaban yang Benar Berdasarkan Materi:', false);
        $reviewPage->assertSee('service apache2 restart', false);
    }

    public function test_answer_highlight_service_bolds_correct_clause_and_escapes_html(): void
    {
        $highlighter = app(AnswerHighlightService::class);
        $studentAnswer = 'Karena dengan begitu kita dapat mendengarkan pendapat dari pihak mana pun dan dengan begitu tidak ada pihak yang merasa di menangkan sehingga tetap menjaga persatuan bersama <script>alert(1)</script>';
        $modelKey = 'Musyawarah untuk mufakat lebih diutamakan agar tidak ada pihak yang merasa dikalahkan atau didominasi, sehingga keharmonisan dan persatuan bersama tetap terjaga.';

        $spans = $highlighter->extractMatchedSpans($studentAnswer, $modelKey);
        $this->assertNotEmpty($spans);
        $this->assertStringContainsString('tidak ada pihak yang merasa di menangkan sehingga tetap menjaga persatuan bersama', $spans[0]);

        $html = $highlighter->formatHighlightedHtml($studentAnswer, $spans, true);
        $this->assertStringContainsString('<strong class="font-bold text-slate-950">tidak ada pihak yang merasa di menangkan sehingga tetap menjaga persatuan bersama</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        // Jika tidak diizinkan highlight (jawaban salah), jangan ada tag strong
        $noHighlightHtml = $highlighter->formatHighlightedHtml('jawaban asal tidak nyambung', $spans, false);
        $this->assertStringNotContainsString('<strong', $noHighlightHtml);
    }

    public function test_generated_quiz_socratic_and_teacher_questions_have_zero_duplicates(): void
    {
        $processor = app(DocumentAiProcessor::class);

        $quiz = $processor->generateHeuristicQuizQuestions($this->sampleMaterial, 'SUB DOMAIN VIRTUAL HOST', 'ADMINISTRASI JARINGAN', 20);
        $this->assertCount(20, $quiz);
        $quizPrompts = array_map(fn ($item) => mb_strtolower(trim($item['question'])), $quiz);
        $this->assertCount(20, array_unique($quizPrompts), 'Semua soal kuis pilihan ganda harus unik tanpa duplikat.');

        $socratic = $processor->generateHeuristicSocraticQuestions($this->sampleMaterial, 'SUB DOMAIN VIRTUAL HOST', 'ADMINISTRASI JARINGAN');
        $teacher = $processor->generateHeuristicTeacherChallengeSuggestions($this->sampleMaterial, 'SUB DOMAIN VIRTUAL HOST', 'ADMINISTRASI JARINGAN');

        $socraticPrompts = array_map(fn ($item) => mb_strtolower(trim($item['prompt'])), $socratic);
        $teacherPrompts = array_map(fn ($item) => mb_strtolower(trim($item['question'])), $teacher);

        $this->assertCount(5, array_unique($socraticPrompts));
        $this->assertCount(5, array_unique($teacherPrompts));
        $this->assertEmpty(array_intersect($socraticPrompts, $teacherPrompts), 'Soal Challenge Socrates dan Tantangan Guru tidak boleh sama persis.');
    }
}
