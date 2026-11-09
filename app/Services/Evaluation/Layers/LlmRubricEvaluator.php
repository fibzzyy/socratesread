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
Tugasmu adalah menilai jawaban esai siswa berdasarkan isi materi bacaan dengan prinsip: MENGHARGAI PARAFRASE & PEMAHAMAN MANDIRI SISWA.

Lakukan pemeriksaan terhadap 3 aspek:
1. Pemahaman Konsep & Argumen:
   - Nilai apakah siswa memahami esensi yang ditanyakan dan menjawab sesuai materi.
   - PENTING: Siswa TIDAK diwajibkan menyalin kalimat teks bacaan atau menulis formalitas seperti "berdasarkan teks". Jika siswa memparafrasekan konsep materi dengan kata-katanya sendiri secara tepat, ini bukti siswa benar-benar paham dan BUKAN sekadar copy-paste. Berikan nilai tinggi (80 - 100)!
   - Jika siswa terindikasi hanya menyalin/copy-paste kalimat mentah tanpa penjelasan sendiri, batas nilai maksimal adalah 55.
2. Kebenaran Faktual:
   - Jika jawaban BENAR dan sesuai fakta materi: beri score (75 - 100), set "understanding_level": "Tepat & Sesuai (Baik)" atau "Sangat Tepat (Mahir)".
   - Jika jawaban HAMPIR BENAR (sebagian benar, namun konsep inti belum lengkap): beri score (40 - 69), set "understanding_level": "Hampir Benar (Sebagian Benar)".
   - Jika jawaban SALAH atau melenceng: beri score rendah (0 - 39), set "understanding_level": "Salah / Belum Tepat".
3. Gaya Penulisan Feedback (Persis Seperti Google NotebookLM):
   - Uraikan analisis secara mengalir yang mengapresiasi kata/poin kunci yang berhasil dipahami siswa.
   - Contoh gaya benar: "Jawaban Anda sudah sangat tepat. Anda berhasil memahami bahwa [poin pemahaman siswa] merupakan esensi penting dari materi ini."
   - Jangan menuntut siswa menambahkan kutipan teks buku jika pemahamannya sudah tepat.

FORMAT OUTPUT WAJIB HANYA BERUPA JSON VALID TANPA MARKDOWN DAN TANPA TEKS LAIN:
{
  "score": 85,
  "understanding_level": "Sangat Tepat (Mahir)",
  "text_alignment": "supported",
  "missing_concepts": [],
  "hint": "",
  "feedback": "Jawaban Anda sudah sangat tepat. Anda berhasil memahami konsep utama dengan menggunakan bahasa dan pemikiran Anda sendiri."
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
            // Menghargai pemahaman dan parafrase mandiri siswa (tanda paham, bukan copas)
            $score = 80;
            if ($wordCount >= 10) $score += 10;
            if ($wordCount >= 20) $score += 10;
            if ($isCopied) $score = min(55, $score - 25);
            $score = min(100, max(75, $score));
            $level = $this->determineLevel($score);
            $feedback = 'Jawaban Anda sudah sangat tepat. Anda berhasil memahami esensi materi dengan baik menggunakan pemikiran dan bahasa Anda sendiri.';
        }

        $missingConcepts = [];
        if ($score < 70 && $wordCount < 15) {
            $missingConcepts[] = 'Pengembangan penjelasan konsep lebih mendalam';
        }
        if ($isCopied) {
            $missingConcepts[] = 'Gunakan penjelasan dengan kata-kata sendiri alih-alih menyalin mentah teks';
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
