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
        'agar', 'supaya', 'sehingga', 'juga', 'secara', 'lebih', 'sangat', 'bahwa',
        'hal', 'apa', 'bagaimana', 'mengapa', 'jelaskan', 'uraikan', 'sebutkan',
    ];

    /**
     * Kelompok padanan kata / sinonim parafrase umum Bahasa Indonesia agar evaluasi
     * mampu mengenali jawaban parafrase siswa yang maknanya setara dengan Kunci Jawaban.
     *
     * @var array<int, array<int, string>>
     */
    protected array $synonymGroups = [
        ['hemat', 'efisien', 'efisiensi', 'irit', 'optimal'],
        ['satu', 'tunggal', 'sama'],
        ['beberapa', 'banyak', 'multi', 'berbagai', 'sekaligus', 'beragam', 'lebih'],
        ['ganti', 'ubah', 'beda', 'pisah', 'isi', 'tampil', 'layan', 'jalan'],
        ['metode', 'cara', 'teknik', 'konfigurasi', 'atur', 'mekanisme', 'sistem', 'strategi'],
        ['tujuan', 'fungsi', 'peran', 'manfaat', 'kegunaan', 'guna'],
        ['padat', 'padu', 'gabung', 'imbang', 'sinergi', 'selaras'],
        ['identitas', 'jati', 'ciri', 'lambang', 'simbol'],
        ['lestari', 'jaga', 'pertahan', 'lindung', 'punah'],
        ['kuasa', 'pelajar', 'paham', 'kompeten', 'mampu'],
        ['simpan', 'tampung', 'muat', 'folder', 'direktori', 'lokasi'],
        ['arah', 'tuju', 'alihan', 'alih', 'rute', 'peta'],
        ['masalah', 'kendala', 'gangguan', 'error', 'rusak', 'gagal'],
        ['periksa', 'cek', 'teliti', 'tinjau', 'evaluasi'],
        ['ulang', 'restart', 'muat', 'refresh', 'f5'],
        ['salin', 'duplikat', 'kopi', 'copy', 'ganda'],
        ['perintah', 'command', 'instruksi', 'eksekusi'],
        ['situs', 'web', 'halaman', 'layanan', 'konten', 'portal'],
        ['kalah', 'dominasi', 'menang', 'sepihak', 'mayoritas'],
        ['harmonis', 'persatuan', 'kesatuan', 'bersama', 'rukun', 'utuh', 'damai'],
        ['dengar', 'terima', 'hargai', 'tampung', 'akomodasi', 'libatkan'],
        ['musyawarah', 'mufakat', 'diskusi', 'berunding', 'sepakat', 'kesepakatan'],
    ];

    /**
     * Evaluasi Lapis 2: Kemiripan semantik/parafrase terhadap Kunci Jawaban & materi, plus deteksi copy-paste.
     *
     * @return array{
     *     is_copied: bool,
     *     copy_ratio: float,
     *     relevance_score: float,
     *     answer_key_coverage: float,
     *     most_relevant_paragraph: ?string,
     *     matched_ngrams_count: int,
     *     matched_key_concepts: array<int, string>,
     *     missing_key_concepts: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    public function evaluate(Document $document, SocraticQuestion $question, string $answerText): array
    {
        $cleanAnswer = trim($answerText);
        $documentBody = (string) $document->body;

        // 1. Deteksi Copy-Paste Mentah (Verbatim Copying)
        $copyMetrics = $this->calculateCopyOverlap($cleanAnswer, $documentBody);

        // 2. Evaluasi Relevansi Materi & Kecocokan Parafrase terhadap Kunci Jawaban
        $relevanceMetrics = $this->calculateQuestionRelevance($cleanAnswer, $question, $documentBody);

        $warnings = [];
        if ($copyMetrics['is_copied']) {
            $warnings[] = 'Jawaban terdeteksi sebagian besar (' . round($copyMetrics['copy_ratio'] * 100) . '%) menyalin langsung kalimat dari bacaan tanpa sintesis sendiri.';
        }

        if ($relevanceMetrics['relevance_score'] < 0.2 && $relevanceMetrics['answer_key_coverage'] < 0.18) {
            $warnings[] = 'Jawaban terindikasi kurang membahas konsep inti yang terdapat pada kunci jawaban maupun materi.';
        }

        return [
            'is_copied' => $copyMetrics['is_copied'],
            'copy_ratio' => $copyMetrics['copy_ratio'],
            'relevance_score' => $relevanceMetrics['relevance_score'],
            'answer_key_coverage' => $relevanceMetrics['answer_key_coverage'],
            'most_relevant_paragraph' => $relevanceMetrics['relevant_paragraph'],
            'matched_ngrams_count' => $copyMetrics['matched_ngrams'],
            'matched_key_concepts' => $relevanceMetrics['matched_key_concepts'],
            'missing_key_concepts' => $relevanceMetrics['missing_key_concepts'],
            'warnings' => $warnings,
        ];
    }

    /**
     * Menghitung rasio kalimat/frasa yang disalin mentah-mentah dari dokumen.
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

        $hasLongExactSubstring = false;
        if (mb_strlen($normalizedAnswer) >= 60) {
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
     * Menghitung akurasi parafrase jawaban siswa terhadap Kunci Jawaban ($question->hint) dan konteks materi.
     *
     * @return array{
     *     relevance_score: float,
     *     answer_key_coverage: float,
     *     relevant_paragraph: ?string,
     *     matched_key_concepts: array<int, string>,
     *     missing_key_concepts: array<int, string>
     * }
     */
    protected function calculateQuestionRelevance(string $answer, SocraticQuestion $question, string $documentBody): array
    {
        $answerTokens = $this->extractKeywords($answer);
        $answerConceptMap = $this->expandConceptMap($answerTokens, $answer);

        $keyText = trim((string) $question->hint);
        $keyTokens = $this->extractKeywords($keyText);
        $questionTokens = $this->extractKeywords($question->prompt . ' ' . $question->context . ' ' . $keyText);

        if (empty($questionTokens) || empty($answerTokens)) {
            return [
                'relevance_score' => 0.0,
                'answer_key_coverage' => 0.0,
                'relevant_paragraph' => null,
                'matched_key_concepts' => [],
                'missing_key_concepts' => [],
            ];
        }

        // 1. Hitung seberapa akurat parafrase siswa mencakup poin-poin Kunci Jawaban
        $matchedKey = [];
        $missingKey = [];
        foreach ($keyTokens as $kTok) {
            if ($this->tokenMatchesConceptMap($kTok, $answerConceptMap, $answer)) {
                $matchedKey[] = $kTok;
            } else {
                $missingKey[] = $kTok;
            }
        }
        $answerKeyCoverage = count($keyTokens) > 0
            ? round(count($matchedKey) / count($keyTokens), 3)
            : 0.0;

        // 2. Cari paragraf bacaan yang paling berhubungan dengan pertanyaan & kunci jawaban
        $paragraphs = array_filter(
            preg_split('/\n{2,}/u', $documentBody) ?: [],
            fn ($p) => mb_strlen(trim($p)) > 35
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

        $intersectWithQuestion = array_filter($questionTokens, fn ($qt) => $this->tokenMatchesConceptMap($qt, $answerConceptMap, $answer));
        $questionOverlap = count($questionTokens) > 0 ? (count($intersectWithQuestion) / count($questionTokens)) : 0.0;

        $bestParaTokens = $bestParagraph ? $this->extractKeywords($bestParagraph) : [];
        $intersectWithPara = array_filter($bestParaTokens, fn ($pt) => $this->tokenMatchesConceptMap($pt, $answerConceptMap, $answer));
        $paraOverlap = count($bestParaTokens) > 0 ? (count($intersectWithPara) / count($bestParaTokens)) : 0.0;

        // Cek juga keseluruhan dokumen (mendukung compound term seperti DocumentRoot <-> Document Root)
        $docTokens = $this->extractKeywords($documentBody);
        $compoundDocText = str_replace(' ', '', $this->normalizeText($documentBody));
        $intersectWithDoc = array_values(array_unique(array_merge(
            array_intersect($docTokens, $answerTokens),
            array_filter($answerTokens, fn (string $tok) => mb_strlen($tok) >= 5 && mb_strpos($compoundDocText, $tok) !== false)
        )));
        $docCoverage = count($answerTokens) > 0 ? (count($intersectWithDoc) / count($answerTokens)) : 0.0;
        $conceptDensityBoost = count($intersectWithDoc) >= 4 ? min(0.45, count($intersectWithDoc) * 0.05) : 0.0;

        $union = array_unique(array_merge($questionTokens, $answerTokens));
        $jaccard = count($union) > 0 ? (count($intersectWithQuestion) / count($union)) : 0.0;

        $relevanceScore = min(1.0, ($jaccard * 1.4) + ($questionOverlap * 0.4) + ($paraOverlap * 0.3) + ($docCoverage * 0.4) + ($answerKeyCoverage * 0.45) + $conceptDensityBoost);

        return [
            'relevance_score' => round($relevanceScore, 3),
            'answer_key_coverage' => $answerKeyCoverage,
            'relevant_paragraph' => $bestParagraph ? mb_substr($bestParagraph, 0, 400) : null,
            'matched_key_concepts' => array_slice($matchedKey, 0, 8),
            'missing_key_concepts' => array_slice($missingKey, 0, 5),
        ];
    }

    /**
     * @param array<int, string> $tokens
     * @return array<string, mixed>
     */
    public function expandConceptMap(array $tokens, string $rawText): array
    {
        $map = [];
        $compactRaw = str_replace(' ', '', $this->normalizeText($rawText));

        foreach ($tokens as $tok) {
            $map[$tok] = true;
            $stem = $this->simpleStem($tok);
            if ($stem !== '') {
                $map[$stem] = true;
            }
            foreach ($this->synonymGroups as $group) {
                foreach ($group as $gWord) {
                    if ($tok === $gWord || $stem === $gWord || str_contains($tok, $gWord) || str_contains($gWord, $stem)) {
                        foreach ($group as $syn) {
                            $map[$syn] = true;
                        }
                        break;
                    }
                }
            }
        }
        $map['__COMPACT__'] = $compactRaw;

        return $map;
    }

    /**
     * @param array<string, mixed> $conceptMap
     */
    public function tokenMatchesConceptMap(string $targetToken, array $conceptMap, string $rawAnswer): bool
    {
        if (isset($conceptMap[$targetToken])) {
            return true;
        }
        $stem = $this->simpleStem($targetToken);
        if ($stem !== '' && isset($conceptMap[$stem])) {
            return true;
        }
        if (mb_strlen($targetToken) >= 5 && isset($conceptMap['__COMPACT__']) && is_string($conceptMap['__COMPACT__'])) {
            if (mb_strpos($conceptMap['__COMPACT__'], $targetToken) !== false) {
                return true;
            }
        }
        foreach ($this->synonymGroups as $group) {
            foreach ($group as $gWord) {
                if ($targetToken === $gWord || $stem === $gWord || str_contains($targetToken, $gWord)) {
                    foreach ($group as $syn) {
                        if (isset($conceptMap[$syn])) {
                            return true;
                        }
                    }
                    break;
                }
            }
        }

        return false;
    }

    protected function simpleStem(string $word): string
    {
        $w = preg_replace('/^(?:meng|meny|mem|men|me|peng|peny|pem|pen|pe|ber|ter|di|ke|se)/u', '', $word) ?? $word;
        $w = preg_replace('/(?:kannya|inya|annya|kan|an|nya|lah|kah|i)$/u', '', $w) ?? $w;

        return mb_strlen($w) >= 3 ? $w : $word;
    }

    /**
     * Ekstrak kata kunci esensial (tanpa stopwords dan simbol).
     *
     * @return array<int, string>
     */
    public function extractKeywords(string $text): array
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

    protected function normalizeText(string $text): string
    {
        $lower = mb_strtolower($text);
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;

        return trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
    }
}
