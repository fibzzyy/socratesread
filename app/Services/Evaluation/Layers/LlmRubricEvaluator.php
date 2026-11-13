<?php

namespace App\Services\Evaluation\Layers;

use App\Models\Document;
use App\Models\SocraticQuestion;
use App\Services\AI\Contracts\LanguageModel;
use App\Services\AI\DocumentAiProcessor;
use App\Services\Evaluation\AnswerHighlightService;
use App\Services\Evaluation\EvaluationResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmRubricEvaluator
{
    public function __construct(
        protected LanguageModel $languageModel,
        protected AnswerHighlightService $highlightService
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
        // Cakupan materi penuh (awal, tengah, hingga akhir) agar soal di bab mana pun tetap terdukung
        $materialExcerpt = app(DocumentAiProcessor::class)->buildFullCoverageContext((string) $document->body, 12000);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Penilai Utama Pembelajaran SocratesRead AI.
Tugasmu adalah menilai makna dan pemahaman jawaban esai (parafrase) siswa terhadap **KUNCI JAWABAN MODEL** dan **ISI MATERI BACAAN**, dengan gaya analisis semantik persis seperti **Google NotebookLM**.

ATURAN PENILAIAN:
1. Fokus pada Esensi & Makna (Bukan Pencocokan Kata Kaku):
   - Siswa TIDAK wajib menyalin istilah buku atau menulis jawaban panjang bertele-tele. Jika 1–2 kalimat siswa dengan bahasa sehari-hari sudah menangkap esensi jawaban dengan tepat, berikan nilai tinggi (80–100)!
   - Toleransi typo / selip istilah ringan: apabila siswa salah ketik atau terbalik istilah kecil (misalnya menulis "di menangkan" padahal konteks kalimatnya jelas bermaksud "tidak ada pihak yang dikalahkan/didominasi"), nilai tetap berdasarkan maksud esensinya dan luruskan istilahnya secara halus pada "feedback".
   - 85 – 100 ("Sangat Tepat (Mahir)"): Jawaban menangkap esensi inti dan alasan/konsep kunci dengan bahasa sendiri secara akurat.
   - 70 – 84 ("Tepat & Sesuai (Baik)"): Inti jawaban benar dan sejalan dengan Kunci Jawaban, hanya ada sedikit nuansa pendukung yang dapat diperkaya.
   - 40 – 69 ("Hampir Benar (Sebagian Benar)"): Jawaban menyentuh sebagian poin kunci namun belum utuh atau melewatkan unsur penting pertanyaan.
   - 0 – 39 ("Salah / Belum Tepat"): Jawaban melenceng, salah konsep secara fundamental, atau bertentangan dengan Kunci Jawaban dan materi.
   - Jika jawaban terindikasi menyalin kalimat bacaan mentah (> 70% verbatim) tanpa parafrase, batas nilai maksimal 55.
2. Gaya Feedback (Persis Seperti Google NotebookLM):
   - Tulis feedback secara hangat, ringkas (1–2 kalimat), dan langsung memparafrasekan esensi apa yang berhasil ditangkap siswa serta pelurusan konsep/istilah bila ada yang kurang lengkap.
3. Sorotan Bukti Jawaban Siswa ("evidence_spans"):
   - Jika skor >= 40, ekstrak 1–2 frasa/klausa PERSIS seperti tertulis di "JAWABAN PARAFRASE SISWA" yang paling jelas menunjukkan inti pemahaman benar tersebut. Jika skor < 40, kembalikan [].

FORMAT OUTPUT WAJIB HANYA BERUPA JSON VALID TANPA MARKDOWN DAN TANPA TEKS LAIN:
{
  "score": 88,
  "understanding_level": "Sangat Tepat (Mahir)",
  "text_alignment": "supported",
  "missing_concepts": [],
  "hint": "",
  "evidence_spans": ["frasa kunci persis dari jawaban siswa yang menunjukkan pemahaman benar"],
  "feedback": "Penjelasan Anda sangat baik. Anda berhasil menangkap esensi materi mengenai [inti konsep yang dipahami siswa] dengan parafrase yang selaras dan tepat."
}
INSTRUCTIONS;

        $userPrompt = "=== INFORMASI BACAAN ===\n"
            . "Judul: {$document->title}\n"
            . "Kategori/Mata Pelajaran: {$document->category}\n"
            . "Teks Bacaan:\n{$materialExcerpt}\n\n"
            . "=== SOAL ===\n"
            . "Keterampilan Berpikir: {$question->skill}\n"
            . "Pertanyaan: {$question->prompt}\n"
            . "Konteks: {$question->context}\n\n"
            . "=== KUNCI JAWABAN MODEL (ACUAN PENILAIAN UTAMA) ===\n"
            . "{$question->hint}\n\n"
            . "=== DATA PEMERIKSAAN AWAL (Lapis 1 & 2) ===\n"
            . "- Kecocokan Konsep Kunci Awal: " . round(($layer2['answer_key_coverage'] ?? 0) * 100) . "%\n"
            . "- Deteksi Copy-Paste Mentah: " . ($layer2['is_copied'] ? 'Terindikasi (' . round($layer2['copy_ratio'] * 100) . '% menyalin)' : 'Orisinal / parafrase') . "\n"
            . "- Skor Relevansi Materi: {$layer2['relevance_score']}\n\n"
            . "=== JAWABAN PARAFRASE SISWA ===\n"
            . "{$answerText}\n\n"
            . "Bandingkan akurasi jawaban siswa terhadap Kunci Jawaban Model di atas dan berikan hasil penilaian JSON:";

        $messages = [
            ['role' => 'user', 'content' => $userPrompt],
        ];

        // Jalankan pemanggilan LLM dengan temperature 0 untuk evaluasi konsisten
        $rawOutput = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.0,
            'max_output_tokens' => 800,
            'timeout' => 25,
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
        $llmSpans = is_array($parsed['evidence_spans'] ?? null) ? $parsed['evidence_spans'] : [];
        $matchedSpans = ($score >= 40)
            ? $this->highlightService->extractMatchedSpans(
                $answerText,
                (string) ($question->hint ?: $question->context),
                $document,
                $llmSpans
            )
            : [];

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
                'matched_spans' => $matchedSpans,
                'layer_3_llm' => [
                    'provider' => config('ai.provider'),
                    'model' => config('ai.provider') === 'gemini'
                        ? config('services.gemini.model')
                        : config('services.openrouter.model'),
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
        $keyCoverage = (float) ($layer2['answer_key_coverage'] ?? 0.0);
        $wordCount = (int) ($layer1['word_count'] ?? 0);
        $isCopied = (bool) ($layer2['is_copied'] ?? false);
        $hasEvidence = (bool) ($layer1['has_evidence'] ?? false);
        $matchedConcepts = is_array($layer2['matched_key_concepts'] ?? null) ? $layer2['matched_key_concepts'] : [];
        $missingKeyTokens = is_array($layer2['missing_key_concepts'] ?? null) ? $layer2['missing_key_concepts'] : [];

        // Gabungkan akurasi terhadap kunci jawaban & relevansi konteks materi secara proporsional
        $combinedAccuracy = max($relevance, ($keyCoverage * 0.65) + ($relevance * 0.45));

        $matchedSpans = [];

        // 1. Salah / Melenceng dari kunci jawaban dan materi
        if ($combinedAccuracy < 0.20 && $keyCoverage < 0.15) {
            $score = 0;
            $level = 'Salah / Belum Tepat';
            $feedback = 'Jawaban belum tepat atau belum menyentuh konsep kunci pada materi bacaan. Pelajari kunci jawaban dan rujukan teks yang benar di bawah ini.';
        } elseif ($combinedAccuracy < 0.40 && $keyCoverage < 0.30) {
            // 2. Hampir Benar: Menjawab sebagian konsep kunci secara proporsional (40 - 68)
            $base = 40 + (int) round(($combinedAccuracy / 0.40) * 18);
            if ($wordCount >= 15) {
                $base += 5;
            }
            if ($hasEvidence) {
                $base += 5;
            }
            if ($isCopied) {
                $base = min(45, $base - 15);
            }
            $score = min(68, max(40, $base));
            $level = 'Hampir Benar (Sebagian Benar)';
            $matchedSpans = $this->highlightService->extractMatchedSpans(
                $answerText,
                (string) ($question->hint ?: $question->context),
                $document
            );
            $conceptSummary = ! empty($matchedConcepts)
                ? ' terkait ' . implode(', ', array_slice($matchedConcepts, 0, 4))
                : '';
            $feedback = 'Jawaban Anda hampir benar dan sudah menyentuh sebagian gagasan materi' . $conceptSummary . ', namun uraiannya masih perlu dilengkapi agar mencakup keseluruhan poin kunci jawaban.';
        } else {
            // 3. Benar / Tepat: Menghitung skor proporsional berdasarkan akurasi parafrase terhadap kunci jawaban (74 - 100)
            $accuracyRatio = min(1.0, max($keyCoverage, $combinedAccuracy));
            $base = 72 + (int) round($accuracyRatio * 20);
            if ($wordCount >= 15) {
                $base += 5;
            }
            if ($wordCount >= 25 || $hasEvidence) {
                $base += 3;
            }
            if ($isCopied) {
                $base = min(55, $base - 30);
            }
            $score = min(100, max(72, $base));
            $level = $this->determineLevel($score);
            $matchedSpans = $this->highlightService->extractMatchedSpans(
                $answerText,
                (string) ($question->hint ?: $question->context),
                $document
            );

            $focusHighlight = ! empty($matchedSpans)
                ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $matchedSpans[0]) ?? ''), 110, '...')
                : implode(', ', array_slice($matchedConcepts, 0, 5));

            if ($score >= 85) {
                $feedback = 'Penjelasan Anda sangat baik. Anda berhasil menangkap esensi pembahasan materi'
                    . ($focusHighlight !== '' ? ' ("' . $focusHighlight . '")' : '')
                    . ' melalui parafrase dengan bahasa Anda sendiri secara tepat dan selaras.';
            } else {
                $feedback = 'Penjelasan Anda sudah tepat dan selaras dengan pembahasan materi'
                    . (! empty($matchedConcepts) ? ' mengenai ' . implode(', ', array_slice($matchedConcepts, 0, 4)) : '')
                    . ', meskipun detail pendukungnya masih dapat diperkuat.';
            }
        }

        $missingConcepts = [];
        if ($score < 85 && ! empty($missingKeyTokens)) {
            $missingConcepts[] = 'Pertajam penjelasan konsep terkait: ' . implode(', ', array_slice($missingKeyTokens, 0, 4));
        }
        if ($score < 70 && $wordCount < 15) {
            $missingConcepts[] = 'Uraikan alasan atau hubungan konsep lebih lengkap agar sesuai kunci jawaban';
        }
        if ($isCopied) {
            $missingConcepts[] = 'Gunakan parafrase dengan kata-kata sendiri alih-alih menyalin mentah teks';
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
                'matched_spans' => $matchedSpans,
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
