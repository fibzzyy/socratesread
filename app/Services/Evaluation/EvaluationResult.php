<?php

namespace App\Services\Evaluation;

class EvaluationResult
{
    /**
     * @param array<int, string> $missingConcepts
     * @param array<string, mixed> $layerDetails
     */
    public function __construct(
        public bool $passed,
        public int $score,
        public string $understandingLevel,
        public string $finalLayer,
        public bool $hasEvidence,
        public bool $isCopied,
        public float $copyRatio,
        public float $relevanceScore,
        public ?string $textAlignment,
        public array $missingConcepts,
        public ?string $hint,
        public string $feedback,
        public array $layerDetails = []
    ) {}

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'score' => $this->score,
            'understanding_level' => $this->understandingLevel,
            'final_layer' => $this->finalLayer,
            'has_evidence' => $this->hasEvidence,
            'is_copied' => $this->isCopied,
            'copy_ratio' => round($this->copyRatio, 4),
            'relevance_score' => round($this->relevanceScore, 4),
            'text_alignment' => $this->textAlignment,
            'missing_concepts' => $this->missingConcepts,
            'hint' => $this->hint,
            'feedback' => $this->feedback,
            'matched_spans' => $this->layerDetails['matched_spans'] ?? [],
            'layer_details' => $this->layerDetails,
        ];
    }
}
