<?php

namespace App\Services\Evaluation\Layers;

use App\Models\Document;
use App\Models\SocraticQuestion;

class SemanticSimilarityEvaluator
{
    /** Stopwords umum bahasa Indonesia untuk memfilter kata fungsional saat menghitung kemiripan kata */
    protected array $stopwords = [
        'yang', 'di', 'ke', 'dari', 'dan', 'atau', 'ini', 'itu', 'untuk', 'dengan',
        'pada', 'adalah', 'yaitu', 'yakni', 'sebagai', 'dalam', 'bisa', 'dapat',
        'oleh', 'karena', 'jika', 'maka', 'akan', 'sudah', 'telah', 'sedang', 'serta',
        'tersebut', 'dia', 'mereka', 'ia', 'kita', 'kami', 'anda', 'kamu', 'saya',
    ];

    /**
     * Evaluasi Lapis 2: Kemiripan semantik/kata kunci dan deteksi copy-paste mentah.
     *
     * @return array{
     *     is_copied: bool,
     *     copy_ratio: float,
     *     relevance_score: float,
     *     most_relevant_paragraph: ?string,
     *     matched_ngrams_count: int,
     *     warnings: array<int, string>
     * }
     */
    public function evaluate(Document $document, SocraticQuestion $question, string $answerText): array
    {
        $cleanAnswer = trim($answerText);
        $documentBody = (string) $document->body;

        // 1. Deteksi Copy-Paste Mentah (Verbatim Copying)
        $copyMetrics = $this->calculateCopyOverlap($cleanAnswer, $documentBody);

        // 2. Evaluasi Relevansi Jawaban terhadap Pertanyaan & Konteks
        $relevanceMetrics = $this->calculateQuestionRelevance($cleanAnswer, $question, $documentBody);

        $warnings = [];
        if ($copyMetrics['is_copied']) {
            $warnings[] = "Jawaban terdeteksi sebagian besar (" . round($copyMetrics['copy_ratio'] * 100) . "%) menyalin langsung kalimat dari bacaan tanpa sintesis sendiri.";
        }

        if ($relevanceMetrics['relevance_score'] < 0.2) {
            $warnings[] = "Jawaban terindikasi kurang membahas kata kunci utama pada pertanyaan atau konteks materi.";
        }

        return [
            'is_copied' => $copyMetrics['is_copied'],
            'copy_ratio' => $copyMetrics['copy_ratio'],
            'relevance_score' => $relevanceMetrics['relevance_score'],
            'most_relevant_paragraph' => $relevanceMetrics['relevant_paragraph'],
            'matched_ngrams_count' => $copyMetrics['matched_ngrams'],
            'warnings' => $warnings,
        ];
    }

    /**
     * Menghitung rasio kalimat/frasa yang disalin mentah-mentah dari dokumen.
     * Menggunakan analisis tumpang tindih 4-gram dan kecocokan substring.
     *
     * @return array{is_copied: bool, copy_ratio: float, matched_ngrams: int}
     */
    protected function calculateCopyOverlap(string $answer, string $documentBody): array
    {
        $normalizedAnswer = $this->normalizeText($answer);
        $normalizedDoc = $this->normalizeText($documentBody);

        $words = preg_split('/\s+/u', $normalizedAnswer, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $totalWords = count($words);

        if ($totalWords < 4) {
            return ['is_copied' => false, 'copy_ratio' => 0.0, 'matched_ngrams' => 0];
        }

        // Buat 4-gram (frasa beruntun 4 kata)
        $n = 4;
        $totalNgrams = max(1, $totalWords - $n + 1);
        $matchedNgrams = 0;

        for ($i = 0; $i <= $totalWords - $n; $i++) {
            $gram = implode(' ', array_slice($words, $i, $n));
            if (mb_strpos($normalizedDoc, $gram) !== false) {
                $matchedNgrams++;
            }
        }

        $ngramRatio = $matchedNgrams / $totalNgrams;

        // Cek juga apakah ada potongan teks berurutan sangat panjang (> 60 karakter) yang identik
        $hasLongExactSubstring = false;
        if (mb_strlen($normalizedAnswer) >= 60) {
            // Coba ambil potongan 60 karakter dari jawaban
            for ($offset = 0; $offset <= mb_strlen($normalizedAnswer) - 60; $offset += 20) {
                $chunk = mb_substr($normalizedAnswer, $offset, 60);
                if (mb_strpos($normalizedDoc, $chunk) !== false) {
                    $hasLongExactSubstring = true;
                    break;
                }
            }
        }

        $isCopied = ($ngramRatio >= 0.70) || ($hasLongExactSubstring && $ngramRatio >= 0.50);

        return [
            'is_copied' => $isCopied,
            'copy_ratio' => $ngramRatio,
            'matched_ngrams' => $matchedNgrams,
        ];
    }

    /**
     * Menghitung relevansi kata kunci antara pertanyaan, jawaban siswa, dan paragraf dokumen terkait.
     *
     * @return array{relevance_score: float, relevant_paragraph: ?string}
     */
    protected function calculateQuestionRelevance(string $answer, SocraticQuestion $question, string $documentBody): array
    {
        $questionTokens = $this->extractKeywords($question->prompt . ' ' . $question->context . ' ' . $question->hint);
        $answerTokens = $this->extractKeywords($answer);

        if (empty($questionTokens) || empty($answerTokens)) {
            return ['relevance_score' => 0.0, 'relevant_paragraph' => null];
        }

        // Cari paragraf bacaan yang paling berhubungan dengan pertanyaan
        $paragraphs = array_filter(
            preg_split('/\n{2,}/u', $documentBody) ?: [],
            fn ($p) => mb_strlen(trim($p)) > 40
        );

        $bestParagraph = null;
        $bestScore = 0.0;

        foreach ($paragraphs as $para) {
            $paraTokens = $this->extractKeywords($para);
            $paraIntersect = array_intersect($questionTokens, $paraTokens);
            $score = count($questionTokens) > 0 ? (count($paraIntersect) / count($questionTokens)) : 0.0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestParagraph = trim($para);
            }
        }

        // Hitung relevansi jawaban siswa terhadap kata kunci pertanyaan
        $intersectWithQuestion = array_intersect($questionTokens, $answerTokens);
        $questionOverlap = count($questionTokens) > 0 ? (count($intersectWithQuestion) / count($questionTokens)) : 0.0;

        // Hitung relevansi jawaban siswa terhadap paragraf materi terkait
        $bestParaTokens = $bestParagraph ? $this->extractKeywords($bestParagraph) : [];
        $intersectWithPara = array_intersect($bestParaTokens, $answerTokens);
        $paraOverlap = count($bestParaTokens) > 0 ? (count($intersectWithPara) / count($bestParaTokens)) : 0.0;

        // Jaccard similarity antara pertanyaan dan jawaban
        $union = array_unique(array_merge($questionTokens, $answerTokens));
        $jaccard = count($union) > 0 ? (count($intersectWithQuestion) / count($union)) : 0.0;

        // Skor relevansi murni mengukur seberapa relevan jawaban siswa
        // Jika jawaban siswa sama sekali tidak mengandung kata kunci (misal: "nasi goreng"), skor akan 0
        $relevanceScore = min(1.0, ($jaccard * 2.0) + ($questionOverlap * 0.5) + ($paraOverlap * 0.4));

        return [
            'relevance_score' => round($relevanceScore, 3),
            'relevant_paragraph' => $bestParagraph ? mb_substr($bestParagraph, 0, 400) : null,
        ];
    }

    /**
     * Ekstrak kata kunci esensial (tanpa stopwords dan simbol).
     *
     * @return array<int, string>
     */
    protected function extractKeywords(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $keywords = [];
        $stopwordMap = array_flip($this->stopwords);

        foreach ($tokens as $token) {
            if (mb_strlen($token) >= 3 && ! isset($stopwordMap[$token])) {
                $keywords[] = $token;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Normalisasi teks untuk perbandingan (huruf kecil, tanpa tanda baca ekstrem).
     */
    protected function normalizeText(string $text): string
    {
        $lower = mb_strtolower($text);
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;
        return trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
    }
}
