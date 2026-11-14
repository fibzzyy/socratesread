<?php

namespace App\Services\Evaluation;

use App\Models\Document;
use App\Services\Evaluation\Layers\SemanticSimilarityEvaluator;

/**
 * Mengidentifikasi frasa/klausa kunci di dalam jawaban esai siswa yang paling menunjukkan
 * kebenaran konsep terhadap Kunci Jawaban & materi, lalu memformatnya dengan bold aman (HTML).
 */
class AnswerHighlightService
{
    public function __construct(
        protected SemanticSimilarityEvaluator $similarity
    ) {}

    /**
     * @param  array<int, string>  $preferredSpans
     * @return array<int, string>
     */
    public function extractMatchedSpans(
        string $studentAnswer,
        string $modelAnswer,
        ?Document $document = null,
        array $preferredSpans = []
    ): array {
        $studentAnswer = trim($studentAnswer);
        if ($studentAnswer === '' || mb_strlen($studentAnswer) < 12) {
            return [];
        }

        // 1. Validasi spans dari LLM (pastikan benar-benar substring asli jawaban siswa & bukan keseluruhan teks)
        $validLlmSpans = [];
        foreach ($preferredSpans as $span) {
            $cleanSpan = preg_replace('/^[\s"“”\']+|[\s"“”\']+$/u', '', (string) $span) ?? trim((string) $span);
            $spanWords = preg_split('/\s+/u', $cleanSpan, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($spanWords) < 2 || mb_strlen($cleanSpan) < 10) {
                continue;
            }
            $pos = mb_stripos($studentAnswer, $cleanSpan);
            if ($pos !== false) {
                $exact = mb_substr($studentAnswer, $pos, mb_strlen($cleanSpan));
                if (! in_array($exact, $validLlmSpans, true)) {
                    $validLlmSpans[] = $exact;
                }
            }
        }
        if (! empty($validLlmSpans)) {
            return array_slice($validLlmSpans, 0, 2);
        }

        // 2. Bangun peta konsep acuan (utama: kunci jawaban, pendukung: ringkasan materi)
        $referenceTokens = $this->similarity->extractKeywords($modelAnswer);
        if (empty($referenceTokens) && $document) {
            $referenceTokens = $this->similarity->extractKeywords((string) ($document->excerpt ?: mb_substr((string) $document->body, 0, 1200)));
        }
        if (empty($referenceTokens)) {
            return [];
        }
        $referenceConceptMap = $this->similarity->expandConceptMap($referenceTokens, $modelAnswer);

        $docConceptMap = [];
        if ($document && trim((string) $document->body) !== '') {
            $docTokens = $this->similarity->extractKeywords(mb_substr((string) $document->body, 0, 3000));
            $docConceptMap = $this->similarity->expandConceptMap($docTokens, (string) $document->body);
        }

        // 3. Pecah jawaban siswa menjadi klausa berurutan beserta offset posisinya
        $clauses = $this->splitAnswerIntoClauses($studentAnswer);
        if (empty($clauses)) {
            return [];
        }

        $scored = [];
        foreach ($clauses as $idx => $clause) {
            $text = $clause['text'];
            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($words) < 3) {
                continue;
            }

            $clauseTokens = $this->similarity->extractKeywords($text);
            if (empty($clauseTokens)) {
                continue;
            }

            $keyMatches = 0;
            $docMatches = 0;
            foreach ($clauseTokens as $tok) {
                if ($this->similarity->tokenMatchesConceptMap($tok, $referenceConceptMap, $modelAnswer)) {
                    $keyMatches++;
                } elseif (! empty($docConceptMap) && $this->similarity->tokenMatchesConceptMap($tok, $docConceptMap, (string) $document?->body)) {
                    $docMatches++;
                }
            }

            // Penalti basa-basi pembuka agar sorotan langsung jatuh pada klausa inti kebenaran
            $fluffPenalty = preg_match('/^(?:karena dengan begitu|menurut pendapat saya|menurut saya|jadi kesimpulannya|pada dasarnya|oleh karena itu)\b/iu', $text) ? 0.8 : 0.0;
            $hasLogicalHook = preg_match('/^(?:sehingga|tidak\b|bukan\b|tanpa\b|agar\b|supaya\b|untuk\b|yaitu\b|adalah\b|merupakan\b|berfungsi\b|memungkinkan\b)/iu', $text) ? 0.35 : 0.0;

            $density = ($keyMatches * 1.6 + $docMatches * 0.45) / max(3, count($clauseTokens));
            $score = ($keyMatches * 1.4) + ($docMatches * 0.35) + $density + $hasLogicalHook - $fluffPenalty;

            if ($keyMatches >= 2 || ($keyMatches >= 1 && $docMatches >= 1)) {
                $scored[] = [
                    'index' => $idx,
                    'start' => $clause['start'],
                    'end' => $clause['end'],
                    'text' => $text,
                    'score' => $score,
                    'keyMatches' => $keyMatches,
                ];
            }
        }

        if (empty($scored)) {
            return [];
        }

        // Gabungkan 2 klausa bersebelahan bila keduanya menyumbang bukti relevan & totalnya tetap terfokus
        $answerLen = mb_strlen($studentAnswer);
        $bestMerged = null;
        for ($i = 0; $i < count($scored) - 1; $i++) {
            $a = $scored[$i];
            $b = $scored[$i + 1];
            if ($b['index'] === $a['index'] + 1 && ($b['start'] - $a['end']) <= 12) {
                $combinedText = trim(mb_substr($studentAnswer, $a['start'], $b['end'] - $a['start']));
                $combinedLen = mb_strlen($combinedText);
                if ($combinedLen <= max(145, (int) floor($answerLen * 0.72))) {
                    $combinedScore = $a['score'] + $b['score'] + 0.5;
                    if ($bestMerged === null || $combinedScore > $bestMerged['score']) {
                        $bestMerged = [
                            'text' => $combinedText,
                            'score' => $combinedScore,
                            'start' => $a['start'],
                            'end' => $b['end'],
                        ];
                    }
                }
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        if ($bestMerged !== null && $bestMerged['score'] >= ($scored[0]['score'] + 1.2)) {
            return [$bestMerged['text']];
        }

        $selected = [];
        $totalHighlightedChars = 0;
        foreach ($scored as $cand) {
            if (count($selected) >= 2) {
                break;
            }
            $candLen = mb_strlen($cand['text']);
            if ($answerLen > 60 && ($totalHighlightedChars + $candLen) > (int) ceil($answerLen * 0.78) && ! empty($selected)) {
                break;
            }
            $selected[] = $cand['text'];
            $totalHighlightedChars += $candLen;
        }

        return $selected;
    }

    /**
     * Memformat teks jawaban siswa menjadi HTML aman dengan bagian `$spans` dicetak tebal (bold).
     *
     * @param  array<int, string>  $spans
     */
    public function formatHighlightedHtml(string $studentAnswer, array $spans, bool $allowHighlight = true): string
    {
        if (! $allowHighlight || empty($spans) || trim($studentAnswer) === '') {
            return e($studentAnswer);
        }

        // Temukan posisi semua span tanpa tumpang tindih
        $ranges = [];
        foreach ($spans as $span) {
            $span = trim((string) $span);
            if ($span === '') {
                continue;
            }
            $pos = mb_stripos($studentAnswer, $span);
            if ($pos === false) {
                continue;
            }
            $end = $pos + mb_strlen($span);
            $overlaps = false;
            foreach ($ranges as $r) {
                if (! ($end <= $r['start'] || $pos >= $r['end'])) {
                    $overlaps = true;
                    break;
                }
            }
            if (! $overlaps) {
                $ranges[] = ['start' => $pos, 'end' => $end];
            }
        }

        if (empty($ranges)) {
            return e($studentAnswer);
        }

        usort($ranges, fn ($a, $b) => $a['start'] <=> $b['start']);

        $html = '';
        $cursor = 0;
        foreach ($ranges as $r) {
            if ($r['start'] > $cursor) {
                $html .= e(mb_substr($studentAnswer, $cursor, $r['start'] - $cursor));
            }
            $matchedPart = mb_substr($studentAnswer, $r['start'], $r['end'] - $r['start']);
            $html .= '<strong class="font-bold text-slate-950">' . e($matchedPart) . '</strong>';
            $cursor = $r['end'];
        }
        if ($cursor < mb_strlen($studentAnswer)) {
            $html .= e(mb_substr($studentAnswer, $cursor));
        }

        return $html;
    }

    /**
     * Memecah teks jawaban menjadi klausa/segmen alami beserta offset karakter multibyte.
     *
     * @return array<int, array{text: string, start: int, end: int}>
     */
    protected function splitAnswerIntoClauses(string $text): array
    {
        $pattern = '/[,;.\n<>]+|\s+(?=sehingga\b|agar\b|supaya\b|karena\b|namun\b|tetapi\b|sedangkan\b|melainkan\b|tidak\s+ada\b|dan\s+dengan\s+begitu\b|yang\s+(?:memungkinkan|berfungsi|bertujuan|menjaga|menjalankan)\b)|(?:\bdan dengan begitu\s+|\bdengan demikian\s+)/iu';

        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [['text' => trim($text), 'start' => 0, 'end' => mb_strlen($text)]];
        }

        $clauses = [];
        $searchOffset = 0;
        foreach ($parts as $rawPart) {
            $trimmed = trim($rawPart);
            if ($trimmed === '') {
                continue;
            }
            $pos = mb_strpos($text, $trimmed, $searchOffset);
            if ($pos === false) {
                continue;
            }
            $end = $pos + mb_strlen($trimmed);
            $clauses[] = [
                'text' => $trimmed,
                'start' => $pos,
                'end' => $end,
            ];
            $searchOffset = $end;
        }

        return $clauses;
    }
}
