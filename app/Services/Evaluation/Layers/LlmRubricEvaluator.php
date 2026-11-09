<?php

namespace App\Services\Evaluation\Layers;

use App\Models\Document;
use App\Models\SocraticQuestion;
use App\Services\AI\Contracts\LanguageModel;
use App\Services\Evaluation\EvaluationResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmRubricEvaluator
{
    public function __construct(
        protected LanguageModel $languageModel
    ) {}

    /**
     * Evaluasi Lapis 3: LLM sebagai Penilai Utama berbasis Rubrik dengan NLI internal.
     *
     * @param array<string, mixed> $layer1
     * @param array<string, mixed> $layer2
     */
    public function evaluate(
        Document $document,
        SocraticQuestion $question,
        string $answerText,
        array $layer1,
        array $layer2
    ): EvaluationResult {
        if ($this->languageModel->isConfigured()) {
            try {
                $llmResult = $this->evaluateWithLlm($document, $question, $answerText, $layer1, $layer2);
                if ($llmResult !== null) {
                    return $llmResult;
                }
            } catch (Throwable $e) {
                Log::warning('LLM Rubric Evaluation failed, falling back to heuristic rubric evaluation.', [
                    'question_id' => $question->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback heuristik berbasis data Lapis 1 & Lapis 2 bila LLM belum dikonfigurasi / error
        return $this->evaluateWithHeuristicRubric($document, $question, $answerText, $layer1, $layer2);
    }

    /**
     * Penilaian menggunakan LLM (temperature: 0, output JSON valid).
     *
     * @param array<string, mixed> $layer1
     * @param array<string, mixed> $layer2
     */
    protected function evaluateWithLlm(
        Document $document,
        SocraticQuestion $question,
        string $answerText,
        array $layer1,
        array $layer2
    ): ?EvaluationResult {
        // Ambil teks materi (maksimal 4500 karakter agar tidak melebihi konteks)
        $materialExcerpt = mb_substr(trim((string) $document->body), 0, 4500);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Penilai Utama Pembelajaran SocratesRead AI untuk siswa SMP/SMA.
Tugasmu adalah menilai jawaban esai Sokratik siswa berdasarkan rubrik analitis dan teks materi bacaan.

Lakukan pemeriksaan mendalam terhadap 3 aspek:
1. Pemahaman Gagasan & Argumen: Apakah siswa menjawab esensi pertanyaan dengan tepat?
2. Kebenaran Faktual:
   - Jika jawaban SALAH, keliru konsep, atau melenceng: beri score rendah (0 - 30), set "understanding_level": "Salah / Belum Tepat", dan jelaskan letak kekeliruannya secara jelas dan tegas.
   - Jika jawaban HAMPIR BENAR (sebagian benar, namun konsep inti belum lengkap atau ada bagian yang kurang tepat): beri score (40 - 69), set "understanding_level": "Hampir Benar (Sebagian Benar)", dan jelaskan apa yang sudah benar serta apa yang masih kurang.
   - Jika jawaban BENAR dan sesuai fakta materi: beri score (75 - 100), set "understanding_level": "Tepat & Sesuai (Baik)" atau "Sangat Tepat (Mahir)".
3. Kualitas Bukti & Orisinalitas:
   - Jika siswa terindikasi hanya menyalin/copy-paste kalimat mentah tanpa penjelasan dengan kata-kata sendiri, batas nilai maksimal adalah 55.

Rubrik Skor (0 - 100):
- 85 - 100 ("Sangat Tepat (Mahir)"): Pemahaman mendalam, bukti teks relevan, penalaran orisinal, NLI "supported".
- 70 - 84 ("Tepat & Sesuai (Baik)"): Menjawab inti pertanyaan dengan benar, ada rujukan bukti, penalaran jelas.
- 40 - 69 ("Hampir Benar (Sebagian Benar)"): Menjawab sebagian gagasan, bukti minim/kurang lengkap, atau konsep belum tuntas.
- 0 - 39 ("Salah / Belum Tepat"): Melenceng dari pertanyaan, NLI "contradicted" (bertentangan fakta), salah konsep, atau jawaban tidak relevan.

FORMAT OUTPUT WAJIB HANYA BERUPA JSON VALID TANPA MARKDOWN DAN TANPA TEKS LAIN:
{
  "score": 85,
  "understanding_level": "Sangat Tepat (Mahir)",
  "text_alignment": "supported",
  "missing_concepts": [
    "Uraian dampak jangka panjang"
  ],
  "hint": "Coba renungkan bagaimana keterkaitan antara paragraf 2 dan 3...",
  "feedback": "Penjelasan Anda runtut dan mengaitkan bukti kutipan dengan sangat baik."
}
INSTRUCTIONS;

        $userPrompt = "=== INFORMASI BACAAN ===\n"
            . "Judul: {$document->title}\n"
            . "Kategori/Mata Pelajaran: {$document->category}\n"
            . "Teks Bacaan:\n{$materialExcerpt}\n\n"
            . "=== PERTANYAAN SOKRATIK ===\n"
            . "Keterampilan Berpikir: {$question->skill}\n"
            . "Pertanyaan: {$question->prompt}\n"
            . "Konteks: {$question->context}\n"
            . "Petunjuk Awal: {$question->hint}\n\n"
            . "=== DATA PEMERIKSAAN AWAL (Lapis 1 & 2) ===\n"
            . "- Bukti/Kutipan Terdeteksi: " . ($layer1['has_evidence'] ? 'Ya' : 'Belum spesifik') . "\n"
            . "- Deteksi Copy-Paste Mentah: " . ($layer2['is_copied'] ? 'Terindikasi (' . round($layer2['copy_ratio'] * 100) . '% menyalin)' : 'Orisinal / parafrase') . "\n"
            . "- Skor Relevansi Awal: {$layer2['relevance_score']}\n\n"
            . "=== JAWABAN SISWA ===\n"
            . "{$answerText}\n\n"
            . "Berikan penilaian Anda sekarang dalam format JSON sesuai instruksi di atas:";

        $messages = [
            ['role' => 'user', 'content' => $userPrompt],
        ];

        // Jalankan pemanggilan LLM dengan temperature 0 untuk evaluasi konsisten
        $rawOutput = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.0,
            'max_output_tokens' => 800,
        ]);

        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawOutput));
        $cleanJson = preg_replace('/\s*```$/', '', (string) $cleanJson);

        $parsed = json_decode((string) $cleanJson, true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            return null;
        }

        $score = max(0, min(100, (int) $parsed['score']));
        $level = (string) ($parsed['understanding_level'] ?? $this->determineLevel($score));
        $textAlignment = (string) ($parsed['text_alignment'] ?? 'supported');
        $missingConcepts = is_array($parsed['missing_concepts'] ?? null) ? $parsed['missing_concepts'] : [];
        $hint = ! empty($parsed['hint']) ? (string) $parsed['hint'] : null;
        $feedback = (string) ($parsed['feedback'] ?? 'Jawaban Anda telah dievaluasi.');

        return new EvaluationResult(
            passed: $score >= 60,
            score: $score,
            understandingLevel: $level,
            finalLayer: 'llm',
            hasEvidence: (bool) ($layer1['has_evidence'] ?? false),
            isCopied: (bool) ($layer2['is_copied'] ?? false),
            copyRatio: (float) ($layer2['copy_ratio'] ?? 0.0),
            relevanceScore: (float) ($layer2['relevance_score'] ?? 0.0),
            textAlignment: in_array($textAlignment, ['supported', 'contradicted', 'unsupported'], true) ? $textAlignment : 'supported',
            missingConcepts: $missingConcepts,
            hint: $hint,
            feedback: $feedback,
            layerDetails: [
                'layer_1_rule_based' => $layer1,
                'layer_2_similarity' => $layer2,
                'layer_3_llm' => [
                    'model' => config('services.openrouter.model'),
                    'temperature' => 0,
                    'text_alignment' => $textAlignment,
                ],
            ]
        );
    }

    /**
     * Rubrik evaluasi deterministik cadangan (jika API LLM offline / belum diset).
     *
     * @param array<string, mixed> $layer1
     * @param array<string, mixed> $layer2
     */
    protected function evaluateWithHeuristicRubric(
        Document $document,
        SocraticQuestion $question,
        string $answerText,
        array $layer1,
        array $layer2
    ): EvaluationResult {
        $relevance = (float) ($layer2['relevance_score'] ?? 0.0);
        $wordCount = (int) ($layer1['word_count'] ?? 0);
        $isCopied = (bool) ($layer2['is_copied'] ?? false);
        $hasEvidence = (bool) ($layer1['has_evidence'] ?? false);

        // 1. Jika relevansi sangat rendah (jawaban melenceng / salah konsep / tidak menjawab pertanyaan)
        if ($relevance < 0.20) {
            $score = 0;
            $level = 'Salah / Belum Tepat';
            $feedback = 'Jawaban belum tepat atau tidak sesuai dengan fakta konsep yang ditanyakan dalam materi bacaan. Pelajari kunci jawaban yang benar di bawah ini.';
        } elseif ($relevance < 0.45) {
            // 2. Hampir Benar: Menjawab sebagian konsep, namun belum lengkap atau bukti kurang
            $score = 45;
            if ($wordCount >= 15) $score += 15;
            if ($hasEvidence) $score += 10;
            if ($isCopied) $score = min(45, $score - 15);
            $score = min(68, max(40, $score));
            $level = 'Hampir Benar (Sebagian Benar)';
            $feedback = 'Jawaban hampir benar dan telah menyentuh sebagian konsep materi, namun uraiannya belum lengkap atau masih memerlukan penjelasan yang lebih tuntas.';
        } else {
            // 3. Benar: Menjawab inti konsep materi dengan relevansi kuat
            $score = 75;
            if ($wordCount >= 15) $score += 10;
            if ($wordCount >= 25) $score += 5;
            if ($hasEvidence) $score += 10;
            if ($isCopied) $score = min(60, $score - 20);
            $score = min(100, max(75, $score));
            $level = $this->determineLevel($score);
            $feedback = 'Jawaban Anda tepat, logis, dan sesuai dengan konsep yang diuraikan dalam teks bacaan.';
        }

        $missingConcepts = [];
        if (! $hasEvidence) {
            $missingConcepts[] = 'Penyertaan bukti kutipan dari teks bacaan';
        }
        if ($wordCount < 15) {
            $missingConcepts[] = 'Pengembangan argumen dan alasan sebab-akibat';
        }
        if ($isCopied) {
            $missingConcepts[] = 'Sintesis orisinal menggunakan pemikiran sendiri';
        }

        return new EvaluationResult(
            passed: $score >= 60,
            score: $score,
            understandingLevel: $level,
            finalLayer: 'heuristic_rubric',
            hasEvidence: $hasEvidence,
            isCopied: $isCopied,
            copyRatio: (float) ($layer2['copy_ratio'] ?? 0.0),
            relevanceScore: $relevance,
            textAlignment: $score < 40 ? 'contradicted' : 'supported',
            missingConcepts: $missingConcepts,
            hint: $question->hint ?: 'Carilah kalimat pendukung di paragraf yang membahas konsep ini.',
            feedback: $feedback,
            layerDetails: [
                'layer_1_rule_based' => $layer1,
                'layer_2_similarity' => $layer2,
                'layer_3_heuristic' => ['mode' => 'fallback_rubric'],
            ]
        );
    }

    protected function determineLevel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Sangat Tepat (Mahir)',
            $score >= 70 => 'Tepat & Sesuai (Baik)',
            $score >= 40 => 'Hampir Benar (Sebagian Benar)',
            default => 'Salah / Belum Tepat',
        };
    }
}
