<?php

namespace App\Services\Evaluation;

use App\Models\Document;
use App\Models\SocraticQuestion;
use App\Services\Evaluation\Layers\LlmRubricEvaluator;
use App\Services\Evaluation\Layers\RuleBasedEvaluator;
use App\Services\Evaluation\Layers\SemanticSimilarityEvaluator;

class AnswerEvaluationPipeline
{
    public function __construct(
        protected RuleBasedEvaluator $ruleBasedEvaluator,
        protected SemanticSimilarityEvaluator $semanticSimilarityEvaluator,
        protected LlmRubricEvaluator $llmRubricEvaluator
    ) {}

    /**
     * Menjalankan evaluasi bertingkat (Hybrid Berlapis):
     * - Lapis 1: Rule-based (panjang minimal, format, keberadaan bukti)
     * - Lapis 2: Semantic Similarity (deteksi copy-paste & relevansi paragraf)
     * - Lapis 3: LLM Rubric Scorer (Penilai utama: skor, level, NLI, missing concepts, hint)
     */
    public function evaluate(Document $document, SocraticQuestion $question, string $answerText): EvaluationResult
    {
        // === LAPIS 1: Rule-Based Checker ===
        $layer1Result = $this->ruleBasedEvaluator->evaluate($answerText);

        // Jika Lapis 1 mendeteksi pelanggaran fatal (kosong, gibberish, < 4 kata),
        // tolak langsung tanpa membuang kuota/token API LLM
        if ($layer1Result['early_rejection'] instanceof EvaluationResult) {
            return $layer1Result['early_rejection'];
        }

        // === LAPIS 2: Semantic Similarity & Plagiarism / Copy-Paste Detection ===
        $layer2Result = $this->semanticSimilarityEvaluator->evaluate($document, $question, $answerText);

        // === LAPIS 3: LLM dengan Rubrik (Penilai Utama, Temperature: 0, Built-in NLI) ===
        return $this->llmRubricEvaluator->evaluate(
            $document,
            $question,
            $answerText,
            $layer1Result,
            $layer2Result
        );
    }
}
