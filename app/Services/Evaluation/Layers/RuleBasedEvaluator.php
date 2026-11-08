<?php

namespace App\Services\Evaluation\Layers;

use App\Services\Evaluation\EvaluationResult;

class RuleBasedEvaluator
{
    /** Minimal kata untuk jawaban analitik yang layak */
    public const MIN_WORD_COUNT = 6;

    /** Minimal karakter */
    public const MIN_CHAR_COUNT = 30;

    /**
     * Pola penanda kutipan atau perujukan bukti dalam teks bahasa Indonesia.
     * @var array<int, string>
     */
    protected array $evidencePatterns = [
        '/["“”\'«»][^"“”\'«»]{5,}["“”\'«»]/u', // Tanda kutip memuat kutipan teks
        '/\b(?:berdasarkan|menurut|merujuk pada|dalam)\s+(?:teks|bacaan|artikel|paragraf|penulis)\b/ui',
        '/\b(?:paragraf\s+(?:ke\s*[-–]?\s*\d+|\d+|pertama|kedua|ketiga|terakhir))\b/ui',
        '/\b(?:disebutkan|dinyatakan|dijelaskan|tertulis|dipaparkan)\s+bahwa\b/ui',
        '/\b(?:bukti|fakta|data|kutipan)\s+(?:bahwa|menunjukkan|menyatakan)\b/ui',
        '/\b(?:hal ini terbukti|hal tersebut dibuktikan|alasannya adalah|karena pada teks)\b/ui',
    ];

    /**
     * Evaluasi Lapis 1: Aturan format, panjang teks, dan keberadaan bukti.
     *
     * @return array{
     *     passed: bool,
     *     word_count: int,
     *     char_count: int,
     *     has_evidence: bool,
     *     evidence_matches: array<int, string>,
     *     issues: array<int, string>,
     *     early_rejection: ?EvaluationResult
     * }
     */
    public function evaluate(string $answerText): array
    {
        $clean = trim($answerText);
        $charCount = mb_strlen($clean);
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        $issues = [];

        // 1. Cek jawaban kosong atau nyaris kosong
        if ($charCount === 0 || $wordCount === 0) {
            return [
                'passed' => false,
                'word_count' => 0,
                'char_count' => 0,
                'has_evidence' => false,
                'evidence_matches' => [],
                'issues' => ['Jawaban masih kosong.'],
                'early_rejection' => new EvaluationResult(
                    passed: false,
                    score: 0,
                    understandingLevel: 'Salah / Belum Tepat',
                    finalLayer: 'rule_based',
                    hasEvidence: false,
                    isCopied: false,
                    copyRatio: 0.0,
                    relevanceScore: 0.0,
                    textAlignment: 'unsupported',
                    missingConcepts: ['Jawaban belum diberikan.'],
                    hint: 'Bacalah kembali pertanyaan dan carilah gagasan kunci dalam materi bacaan.',
                    feedback: 'Jawaban Anda belum diisi atau kosong sehingga dinyatakan salah. Silakan tuliskan analisis Anda.',
                    layerDetails: ['rule_based' => ['status' => 'empty_answer']]
                ),
            ];
        }

        // 2. Deteksi teks repetitif/gibberish (misal: "aaaaaaa", "asdfasdfasdf", "tidak tahu tidak tahu")
        if ($this->isGibberishOrRepetitive($clean, $words)) {
            return [
                'passed' => false,
                'word_count' => $wordCount,
                'char_count' => $charCount,
                'has_evidence' => false,
                'evidence_matches' => [],
                'issues' => ['Teks terdeteksi bukan kalimat bermakna.'],
                'early_rejection' => new EvaluationResult(
                    passed: false,
                    score: 0,
                    understandingLevel: 'Salah / Belum Tepat',
                    finalLayer: 'rule_based',
                    hasEvidence: false,
                    isCopied: false,
                    copyRatio: 0.0,
                    relevanceScore: 0.0,
                    textAlignment: 'unsupported',
                    missingConcepts: ['Penalaran logis', 'Penyusunan kalimat analitis'],
                    hint: 'Tuliskan jawaban menggunakan kalimat utuh yang menjelaskan pemikiran Anda.',
                    feedback: 'Jawaban Anda tidak terbaca sebagai kalimat penjelasan yang bermakna sehingga dinyatakan salah (0 poin).',
                    layerDetails: ['rule_based' => ['status' => 'gibberish_detected', 'word_count' => $wordCount]]
                ),
            ];
        }

        // 3. Cek panjang minimal
        if ($wordCount < self::MIN_WORD_COUNT || $charCount < self::MIN_CHAR_COUNT) {
            $issues[] = "Jawaban terlalu singkat (baru {$wordCount} kata, minimal disarankan ".self::MIN_WORD_COUNT." kata).";

            // Jika terlalu ekstrem pendek (< 4 kata), tolak langsung tanpa membuang token LLM
            if ($wordCount < 4) {
                return [
                    'passed' => false,
                    'word_count' => $wordCount,
                    'char_count' => $charCount,
                    'has_evidence' => false,
                    'evidence_matches' => [],
                    'issues' => $issues,
                    'early_rejection' => new EvaluationResult(
                        passed: false,
                        score: 0,
                        understandingLevel: 'Salah / Belum Tepat',
                        finalLayer: 'rule_based',
                        hasEvidence: false,
                        isCopied: false,
                        copyRatio: 0.0,
                        relevanceScore: 0.0,
                        textAlignment: 'unsupported',
                        missingConcepts: ['Uraian argumen lengkap', 'Penjelasan bukti'],
                        hint: 'Kembangkan jawabanmu dengan menambahkan alasan mengapa hal itu terjadi dan berikan contoh dari teks.',
                        feedback: "Jawaban Anda terlalu singkat ({$wordCount} kata). Tantangan Sokratik membutuhkan penjelasan dengan argumen dan bukti yang memadai.",
                        layerDetails: ['rule_based' => ['status' => 'too_short', 'word_count' => $wordCount]]
                    ),
                ];
            }
        }

        // 4. Deteksi bukti / kutipan teks
        $evidenceMatches = [];
        foreach ($this->evidencePatterns as $pattern) {
            if (preg_match_all($pattern, $clean, $matches)) {
                foreach ($matches[0] as $match) {
                    $evidenceMatches[] = trim($match);
                }
            }
        }

        $hasEvidence = count($evidenceMatches) > 0;
        if (! $hasEvidence) {
            $issues[] = 'Belum mencantumkan kutipan atau rujukan bukti konkret dari teks bacaan.';
        }

        return [
            'passed' => true,
            'word_count' => $wordCount,
            'char_count' => $charCount,
            'has_evidence' => $hasEvidence,
            'evidence_matches' => array_unique($evidenceMatches),
            'issues' => $issues,
            'early_rejection' => null,
        ];
    }

    /**
     * Memeriksa apakah karakter berulang tidak wajar atau kata diulang-ulang.
     *
     * @param array<int, string> $words
     */
    protected function isGibberishOrRepetitive(string $text, array $words): bool
    {
        // Pengulangan satu karakter beruntun > 5 kali (misal "aaaaaa")
        if (preg_match('/(.)\1{5,}/u', $text)) {
            return true;
        }

        // Jika jumlah kata unik sangat sedikit padahal kata banyak (misal "ya ya ya ya ya")
        if (count($words) >= 4) {
            $uniqueWords = array_unique(array_map('mb_strtolower', $words));
            if (count($uniqueWords) === 1) {
                return true;
            }
            if (count($words) >= 6 && (count($uniqueWords) / count($words)) < 0.3) {
                return true;
            }
        }

        return false;
    }
}
