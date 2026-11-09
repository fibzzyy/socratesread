<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\AI\Contracts\LanguageModel;
use App\Services\Evaluation\AnswerEvaluationPipeline;
use App\Services\Evaluation\Layers\RuleBasedEvaluator;
use App\Services\Evaluation\Layers\SemanticSimilarityEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AnswerEvaluationPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setupScenario(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Ekosistem Hutan Hujan Tropis',
            'category' => 'Biologi',
            'target_grade' => 'all',
            'body' => "Hutan hujan tropis memiliki keanekaragaman hayati yang sangat tinggi dan berfungsi sebagai paru-paru dunia. Kerusakan pada lapisan kanopi dapat memicu pemanasan lokal dan hilangnya habitat satwa endemik.",
            'excerpt' => 'Mengenal peran ekosistem hutan hujan bagi kestabilan iklim bumi.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $question = SocraticQuestion::query()->create([
            'document_id' => $document->id,
            'sequence' => 1,
            'skill' => 'Menemukan bukti',
            'prompt' => 'Apa bukti dalam teks bahwa kerusakan kanopi berdampak pada iklim mikro?',
            'context' => 'Perhatikan bagian yang membahas kanopi hutan.',
            'hint' => 'Cari kata kunci dampak dan lapisan kanopi.',
        ]);

        $session = ReadingSession::query()->create([
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'in_progress',
            'progress_percent' => 0,
        ]);

        return [$student, $document, $question, $session];
    }

    public function test_layer_1_rejects_empty_or_gibberish_answers_immediately(): void
    {
        $evaluator = app(RuleBasedEvaluator::class);

        // Jawaban kosong
        $resEmpty = $evaluator->evaluate('   ');
        $this->assertFalse($resEmpty['passed']);
        $this->assertNotNull($resEmpty['early_rejection']);
        $this->assertSame(0, $resEmpty['early_rejection']->score);

        // Jawaban gibberish berulang
        $resGibberish = $evaluator->evaluate('aaaaaaaaaaaaaa');
        $this->assertFalse($resGibberish['passed']);
        $this->assertNotNull($resGibberish['early_rejection']);
        $this->assertSame('Salah / Belum Tepat', $resGibberish['early_rejection']->understandingLevel);

        // Jawaban terlalu pendek (< 4 kata)
        $resShort = $evaluator->evaluate('hutan rusak parah');
        $this->assertFalse($resShort['passed']);
        $this->assertNotNull($resShort['early_rejection']);
    }

    public function test_layer_2_detects_pure_verbatim_copy_paste(): void
    {
        [, $document, $question] = $this->setupScenario();
        $similarity = app(SemanticSimilarityEvaluator::class);

        // Jawaban menyalin persis kalimat bacaan tanpa sintesis sendiri
        $copiedAnswer = "Kerusakan pada lapisan kanopi dapat memicu pemanasan lokal dan hilangnya habitat satwa endemik.";
        $result = $similarity->evaluate($document, $question, $copiedAnswer);

        $this->assertTrue($result['is_copied']);
        $this->assertGreaterThanOrEqual(0.7, $result['copy_ratio']);
    }

    public function test_layer_3_evaluates_with_mocked_llm_json_rubric_and_nli(): void
    {
        [$student, $document, $question, $session] = $this->setupScenario();

        $mockLlm = Mockery::mock(LanguageModel::class);
        $mockLlm->shouldReceive('isConfigured')->andReturn(true);
        $mockLlm->shouldReceive('respond')->once()->andReturn(json_encode([
            'score' => 92,
            'understanding_level' => 'Mahir',
            'text_alignment' => 'supported',
            'missing_concepts' => ['Elaborasi dampak ke satwa spesifik'],
            'hint' => 'Perhatikan juga hubungan dengan kelembapan udara.',
            'feedback' => 'Analisis sangat cermat dengan mengutip bukti kerusakan kanopi secara tepat.',
        ]));
        $this->app->instance(LanguageModel::class, $mockLlm);

        $pipeline = $this->app->make(AnswerEvaluationPipeline::class);
        $evalResult = $pipeline->evaluate(
            $document,
            $question,
            'Berdasarkan teks bacaan, bukti kerusakannya adalah kanopi yang rusak memicu pemanasan lokal dan mengancam satwa endemik.'
        );

        $this->assertTrue($evalResult->passed);
        $this->assertSame(92, $evalResult->score);
        $this->assertSame('Mahir', $evalResult->understandingLevel);
        $this->assertSame('supported', $evalResult->textAlignment);
        $this->assertSame('llm', $evalResult->finalLayer);
    }

    public function test_student_saving_answer_triggers_evaluation_and_persists_to_database(): void
    {
        [$student, , $question, $session] = $this->setupScenario();

        $response = $this->actingAs($student)->postJson(route('challenge.answer'), [
            'session_id' => $session->id,
            'question_id' => $question->id,
            'answer' => 'Berdasarkan teks bacaan pada paragraf kedua, kanopi yang rusak mengakibatkan pemanasan suhu lokal dan punahnya satwa endemik.',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'saved',
                'evaluation' => [
                    'passed',
                    'score',
                    'understanding_level',
                    'final_layer',
                    'has_evidence',
                    'text_alignment',
                    'feedback',
                ],
            ]);

        $this->assertDatabaseHas('student_answers', [
            'reading_session_id' => $session->id,
            'socratic_question_id' => $question->id,
        ]);

        $savedAnswer = StudentAnswer::where('reading_session_id', $session->id)->first();
        $this->assertNotNull($savedAnswer->score);
        $this->assertNotNull($savedAnswer->understanding_level);
        $this->assertIsArray($savedAnswer->evaluation);
    }
}
