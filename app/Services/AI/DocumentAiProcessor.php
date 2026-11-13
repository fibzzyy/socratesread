<?php

namespace App\Services\AI;

use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Services\AI\Contracts\LanguageModel;
use App\Services\Evaluation\Layers\SemanticSimilarityEvaluator;
use App\Services\ReadingMaterialFormatter;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentAiProcessor
{
    protected ReadingMaterialFormatter $formatter;
    protected SemanticSimilarityEvaluator $similarity;

    public function __construct(
        protected LanguageModel $languageModel,
        ?ReadingMaterialFormatter $formatter = null,
        ?SemanticSimilarityEvaluator $similarity = null
    ) {
        $this->formatter = $formatter ?? new ReadingMaterialFormatter();
        $this->similarity = $similarity ?? new SemanticSimilarityEvaluator();
    }

    /**
     * @return array{
     *     cleaned_body: string,
     *     excerpt: string,
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string, difficulty: string}>,
     *     quiz_questions: array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     * }
     */
    public function process(string $rawText, string $title, ?string $category = null): array
    {
        $cleanedFullBody = $this->cleanText($rawText);

        if ($this->languageModel->isConfigured()) {
            @set_time_limit(180);
            try {
                $aiResult = $this->processWithAi($cleanedFullBody, $title, $category);
                if ($aiResult !== null) {
                    // Jangan pernah membiarkan AI memotong atau meringkas isi teks materi asli
                    $aiBodyLen = mb_strlen(trim((string) ($aiResult['cleaned_body'] ?? '')));
                    $fullLen = mb_strlen(trim($cleanedFullBody));
                    if ($aiBodyLen < (int) ($fullLen * 0.85)) {
                        $aiResult['cleaned_body'] = $cleanedFullBody;
                    }

                    return $aiResult;
                }
            } catch (Throwable $e) {
                Log::warning('Document AI structuring failed, falling back to heuristic processor.', [
                    'error' => $e->getMessage(),
                    'title' => $title,
                ]);
            }
        }

        return $this->processWithHeuristics($cleanedFullBody, $title, $category);
    }

    /**
     * Membangun konteks representatif seluruh bagian materi (awal, tengah, hingga akhir).
     * Jika materi lebih panjang dari $maxChars, seluruh paragraf dipadatkan secara berurutan
     * sehingga AI tetap membaca isi materi dari awal sampai akhir tanpa bagian yang terlewat.
     */
    public function buildFullCoverageContext(string $body, int $maxChars = 18000): string
    {
        $clean = trim($body);
        $len = mb_strlen($clean);
        if ($len <= $maxChars) {
            return $clean;
        }

        $paragraphs = array_values(array_filter(
            preg_split('/\R{2,}/u', $clean) ?: [],
            fn (string $p): bool => trim($p) !== ''
        ));

        if (count($paragraphs) <= 2) {
            $segments = 8;
            $chunkSize = max(400, intdiv($maxChars - 160, $segments));
            $parts = [];
            for ($i = 0; $i < $segments; $i++) {
                $start = $i === ($segments - 1)
                    ? max(0, $len - $chunkSize)
                    : max(0, intdiv($i * ($len - $chunkSize), max(1, $segments - 1)));
                $parts[] = '[BAGIAN ' . ($i + 1) . "/{$segments}]\n" . mb_substr($clean, $start, $chunkSize);
            }

            return implode("\n\n...\n\n", $parts);
        }

        $totalParagraphs = count($paragraphs);
        $perParaBudget = max(160, intdiv($maxChars - ($totalParagraphs * 18), $totalParagraphs));
        $parts = [];
        $currentLen = 0;

        foreach ($paragraphs as $idx => $para) {
            $pTrim = trim(preg_replace('/\s+/u', ' ', $para) ?? $para);
            if (mb_strlen($pTrim) > $perParaBudget) {
                $headLen = (int) floor($perParaBudget * 0.65);
                $tailLen = max(40, $perParaBudget - $headLen - 5);
                $pTrim = mb_substr($pTrim, 0, $headLen) . ' ... ' . mb_substr($pTrim, -$tailLen);
            }
            $block = '[Paragraf ' . ($idx + 1) . "] {$pTrim}";
            if ($currentLen + mb_strlen($block) + 2 > $maxChars) {
                break;
            }
            $parts[] = $block;
            $currentLen += mb_strlen($block) + 2;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return array{
     *     cleaned_body: string,
     *     excerpt: string,
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string, difficulty: string}>,
     *     quiz_questions: array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     * }|null
     */
    protected function processWithAi(string $formattedBody, string $title, ?string $category = null): ?array
    {
        $sampleText = $this->buildFullCoverageContext($formattedBody, 18000);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Kurator Literasi & Asesor Berpikir Kritis Taksonomi Bloom SocratesRead AI.
Tugasmu adalah membaca KESELURUHAN isi materi bacaan (dari paragraf awal hingga penutup) lalu menyusun soal esai & kuis yang valid, kritis, serta kunci jawaban akurat.

Format output WAJIB HANYA berupa JSON valid tanpa teks lain:
{
  "cleaned_body": "teks materi lengkap jika dirapikan (jangan dipotong/diringkas), atau kosongkan string jika teks sudah rapi",
  "excerpt": "Ringkasan intisari materi 2-3 kalimat yang padat dan jelas...",
  "questions": [
    {
      "s": "C4 Menganalisis",
      "d": "easy",
      "q": "Pertanyaan memakai kata kerja operasional Bloom (C1-C5) dan TIDAK menyalin paragraf utuh",
      "c": "Konteks singkat atau fokus pemikiran yang membantu siswa mengarahkan jawaban",
      "h": "Kunci jawaban model berparafrase rapi (diambil dari paragraf yang benar-benar menjawab 'q', 100% langsung menjawab setiap poin pertanyaan)",
      "r": "Kutipan kalimat asli dari paragraf materi yang menjadi bukti langsung kunci jawaban"
    }
  ],
  "quiz_questions": [
    {
      "q": "Pertanyaan PG dengan kata kerja Bloom (C1 Mengingat, C2 Memahami, C3 Menerapkan, C4 Menganalisis, C5 Mengevaluasi) tanpa menyalin paragraf utuh?",
      "a": "Pilihan A (ringkas, sejenis, masuk akal)",
      "b": "Pilihan B (ringkas, sejenis, masuk akal)",
      "c": "Pilihan C (ringkas, sejenis, masuk akal)",
      "d": "Pilihan D (ringkas, sejenis, masuk akal)",
      "k": "A",
      "e": "Penjelasan lengkap MENGAPA pilihan tersebut benar berdasarkan paragraf sumber di materi."
    }
  ]
}

ATURAN KETAT:
1. questions: Wajib 5 butir esai berjenjang Taksonomi Bloom C1 sampai C5 (urutan: C1/C2 Mengingat-Memahami [easy], C2 Memahami [easy], C3 Menerapkan [medium], C4 Menganalisis [medium], C5 Mengevaluasi [hard]).
   - Gunakan kata kerja Bloom tepat (Identifikasi/Jelaskan/Terapkan/Analisis/Evaluasi) dan DILARANG menyalin paragraf utuh sebagai soal maupun mengawali dengan "Berdasarkan teks materi...".
   - Field "h" (kunci jawaban) WAJIB bersumber dari paragraf yang benar-benar menjawab "q", diparafrase rapi dan langsung menjawab inti pertanyaan secara lengkap.
2. quiz_questions: Wajib 10 butir pilihan ganda yang tersebar merata (awal–akhir bacaan) dan mencakup tingkat Bloom C1 sampai C5 (termasuk minimal 2 soal C5 Mengevaluasi).
   - Soal TIDAK boleh menyalin paragraf/kalimat utuh; opsi jawaban harus ringkas (bukan potongan acak/daftar pustaka/topik lain) dan seluruh pengecoh harus masuk akal serta satu kategori dengan kunci jawaban.
   - DILARANG membuat soal dari tujuan pembelajaran ("Menjelaskan pengertian...") atau daftar pustaka.
3. Kunci 'k' adalah tepat satu huruf: A, B, C, atau D.
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nMata Pelajaran/Kategori: {$category}\n\nTeks Materi Lengkap:\n{$sampleText}",
            ],
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.1,
            'max_output_tokens' => 4500,
            'timeout' => 120,
        ]);

        $parsed = $this->decodeJsonObject($rawResponse);
        if (! is_array($parsed) || (empty($parsed['cleaned_body']) && empty($parsed['questions']) && empty($parsed['quiz_questions']))) {
            return null;
        }

        $aiCleaned = trim((string) ($parsed['cleaned_body'] ?? ''));
        $finalBody = $aiCleaned !== '' ? $aiCleaned : $formattedBody;

        $defaultEssaySkills = [
            1 => 'Pengertian & Konsep Utama',
            2 => 'Fungsi & Peranan Utama',
            3 => 'Analisis Komponen & Hubungan',
            4 => 'Analisis Kasus & Sebab-Akibat',
            5 => 'Solusi & Penerapan Nyata',
        ];
        $defaultDifficulties = [1 => 'easy', 2 => 'easy', 3 => 'medium', 4 => 'medium', 5 => 'hard'];

        $questions = [];
        if (! empty($parsed['questions']) && is_array($parsed['questions'])) {
            foreach ($parsed['questions'] as $idx => $q) {
                $seq = $idx + 1;
                $prompt = $this->stripClichéPrefix((string) ($q['q'] ?? $q['prompt'] ?? ''));
                if ($prompt === '') {
                    continue;
                }

                $diff = strtolower((string) ($q['d'] ?? $q['difficulty'] ?? $defaultDifficulties[$seq] ?? 'medium'));
                if (! in_array($diff, ['easy', 'medium', 'hard'], true)) {
                    $diff = $defaultDifficulties[$seq] ?? 'medium';
                }

                $validatedEssay = $this->verifyAndRepairEssayPair(
                    $prompt,
                    (string) ($q['h'] ?? $q['hint'] ?? ''),
                    (string) ($q['r'] ?? $q['reference_excerpt'] ?? ''),
                    $formattedBody,
                    $title
                );

                $questions[] = [
                    'sequence' => $seq,
                    'skill' => (string) ($q['s'] ?? $q['skill'] ?? $defaultEssaySkills[$seq] ?? 'Menjelaskan alasan'),
                    'prompt' => $prompt,
                    'context' => (string) ($q['c'] ?? $q['context'] ?? 'Uraikan konsep inti beserta contoh atau alasan logisnya.'),
                    'hint' => $validatedEssay['answer_key'],
                    'reference_excerpt' => $validatedEssay['reference_excerpt'],
                    'difficulty' => $diff,
                ];
                if ($seq >= 5) {
                    break;
                }
            }
        }

        if (count($questions) < 5) {
            $heuristic = $this->processWithHeuristics($formattedBody, $title, $category);
            for ($i = count($questions) + 1; $i <= 5; $i++) {
                if (isset($heuristic['questions'][$i - 1])) {
                    $item = $heuristic['questions'][$i - 1];
                    $item['sequence'] = $i;
                    $questions[] = $item;
                }
            }
        }

        $quizQuestions = [];
        if (! empty($parsed['quiz_questions']) && is_array($parsed['quiz_questions'])) {
            foreach ($parsed['quiz_questions'] as $idx => $qq) {
                $seq = $idx + 1;
                $qText = (string) ($qq['q'] ?? $qq['question'] ?? '');
                if ($qText === '') {
                    continue;
                }

                $options = [];
                if (isset($qq['options']) && is_array($qq['options'])) {
                    foreach ($qq['options'] as $opt) {
                        $options[] = [
                            'key' => strtoupper((string) ($opt['key'] ?? 'A')),
                            'text' => (string) ($opt['text'] ?? ''),
                        ];
                    }
                } elseif (isset($qq['a'], $qq['b'], $qq['c'], $qq['d'])) {
                    $options = [
                        ['key' => 'A', 'text' => (string) $qq['a']],
                        ['key' => 'B', 'text' => (string) $qq['b']],
                        ['key' => 'C', 'text' => (string) $qq['c']],
                        ['key' => 'D', 'text' => (string) $qq['d']],
                    ];
                }

                $correctKey = strtoupper((string) ($qq['k'] ?? $qq['correct_answer'] ?? 'A'));
                if (! in_array($correctKey, ['A', 'B', 'C', 'D'], true)) {
                    $correctKey = 'A';
                }

                $rawQuizItem = [
                    'sequence' => $seq,
                    'question' => $qText,
                    'options' => $options,
                    'correct_answer' => $correctKey,
                    'explanation' => (string) ($qq['e'] ?? $qq['explanation'] ?? 'Pembahasan berdasarkan konsep utama dalam materi.'),
                ];
                $sanitized = $this->sanitizeQuizQuestionItem($rawQuizItem, $formattedBody);
                if ($sanitized === null) {
                    continue;
                }
                $sanitized['sequence'] = count($quizQuestions) + 1;
                $quizQuestions[] = $sanitized;
                if (count($quizQuestions) >= 10) {
                    break;
                }
            }
        }

        if (count($quizQuestions) < 10) {
            $additionalQuiz = $this->generateHeuristicQuizQuestions($formattedBody, $title, $category, 10);
            for ($i = count($quizQuestions) + 1; $i <= 10; $i++) {
                if (isset($additionalQuiz[$i - 1])) {
                    $item = $additionalQuiz[$i - 1];
                    $item['sequence'] = $i;
                    $quizQuestions[] = $item;
                }
            }
        }

        return [
            'cleaned_body' => $finalBody,
            'excerpt' => (string) ($parsed['excerpt'] ?? $this->generateHeuristicExcerpt($formattedBody, $title)),
            'questions' => $questions,
            'quiz_questions' => $quizQuestions,
        ];
    }

    /**
     * Menghasilkan 5 rekomendasi soal Tantangan Guru yang kritis & langsung menguji isi materi
     * lengkap dengan level kesulitan (easy/medium/hard), konteks terpisah, dan kunci jawaban akurat.
     *
     * @return array<int, array{type: string, question: string, context: string, rubric: string, difficulty: string, difficulty_weight: int}>
     */
    public function generateTeacherChallengeSuggestions(string $body, string $title, ?string $category = null): array
    {
        $formattedBody = $this->cleanText($body);

        if ($this->languageModel->isConfigured()) {
            @set_time_limit(180);
            try {
                $sampleText = $this->buildFullCoverageContext($formattedBody, 18000);
                $instructions = <<<INSTRUCTIONS
Kamu adalah Perancang Soal Berpikir Kritis Taksonomi Bloom (C1–C5) SocratesRead AI.
Tugasmu: baca KESELURUHAN isi materi dari paragraf awal hingga akhir, lalu buat tepat 5 rekomendasi soal ESAI yang mengikuti kata kerja operasional Taksonomi Bloom C1 sampai C5 dan menguji penalaran kritis siswa.

Format output WAJIB HANYA berupa JSON array valid:
[
  {
    "bloom_level": "C1",
    "difficulty": "easy",
    "question": "Pertanyaan memakai templat kata kerja Bloom yang tepat, TIDAK menyalin paragraf utuh, dan tanpa awalan 'Berdasarkan teks materi...'",
    "rubric": "Kunci jawaban model berparafrase rapi yang diambil dari paragraf sumber yang BENAR-BENAR menjawab seluruh poin pada 'question'",
    "reference_excerpt": "Kutipan kalimat asli dari paragraf sumber yang menjadi bukti langsung kunci jawaban"
  }
]

ATURAN WAJIB:
1. WAJIB BERJENJANG TAKSONOMI BLOOM C1–C5: Gunakan kata kerja operasional Bloom yang tepat pada setiap nomor:
   - Soal 1 — C1/C2 (Mengingat & Memahami, "easy"): Identifikasi & jelaskan gagasan inti serta alasan batasannya.
   - Soal 2 — C2/C3 (Memahami & Menerapkan, "medium"): Bandingkan atau jelaskan penerapan konsep/kedudukan pada konteks nyata.
   - Soal 3 — C4 (Menganalisis, "medium"): Analisis unsur, perbedaan, landasan aturan, atau keterkaitan sebab-akibat.
   - Soal 4 — C3/C4 (Menerapkan & Menganalisis Kasus, "hard"): Terapkan konsep untuk memulihkan atau memecahkan masalah di sekolah/masyarakat.
   - Soal 5 — C5 (Mengevaluasi, "hard"): Wajib menggunakan kata kerja Mengevaluasi/Nilai/Kritisi konsekuensi jangka panjang atau dilema di era digital/masyarakat.
2. DILARANG MENYALIN PARAGRAF UTUH KE SOAL: Soal harus ringkas, berfokus pada konsep patokan yang jelas (DILARANG memakai singkatan samar seperti "topik TBB"), dan tidak mengulang kalimat panjang mentah dari bacaan.
3. KUNCI DARI PARAGRAF PENJAWAB: Pastikan "rubric" benar-benar menjawab apa yang ditanyakan pada "question" (bukan sekadar ringkasan acak, label "Kedudukan: ...", atau daftar tujuan pembelajaran/pustaka).
4. DILARANG memakai frasa canggung seperti "Berdasarkan teks materi...", "Menurut bacaan di atas...", atau menambahkan field "context".
5. Field "reference_excerpt" WAJIB berupa kutipan kalimat asli dari paragraf sumber yang tepat mendukung "question" & "rubric".
6. Keluarkan HANYA JSON array tanpa markdown/komentar lain.
INSTRUCTIONS;

                $messages = [
                    [
                        'role' => 'user',
                        'content' => "Judul Materi: {$title}\nMata Pelajaran: {$category}\n\nIsi Materi Lengkap:\n{$sampleText}",
                    ],
                ];

                $raw = $this->languageModel->respond($messages, $instructions, [
                    'temperature' => 0.2,
                    'max_output_tokens' => 2800,
                    'timeout' => 60,
                ]);

                $data = $this->decodeJsonArray($raw);
                if (is_array($data) && ! empty($data)) {
                    $results = [];
                    foreach ($data as $item) {
                        $q = $this->stripClichéPrefix((string) ($item['question'] ?? ''));
                        $rubric = trim((string) ($item['rubric'] ?? ''));
                        if ($q === '' || $rubric === '') {
                            continue;
                        }
                        $validated = $this->verifyAndRepairEssayPair(
                            $q,
                            $rubric,
                            trim((string) ($item['reference_excerpt'] ?? $item['r'] ?? '')),
                            $formattedBody,
                            $title
                        );
                        $diff = strtolower(trim((string) ($item['difficulty'] ?? 'medium')));
                        if (! in_array($diff, ['easy', 'medium', 'hard'], true)) {
                            $diff = 'medium';
                        }
                        $results[] = [
                            'type' => 'essay',
                            'question' => $q,
                            'context' => null,
                            'rubric' => $validated['answer_key'],
                            'reference_excerpt' => $validated['reference_excerpt'],
                            'difficulty' => $diff,
                            'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
                        ];
                        if (count($results) >= 5) {
                            break;
                        }
                    }

                    if (count($results) >= 3) {
                        return $results;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('AI teacher challenge suggestions failed, using smart fallback.', [
                    'error' => $e->getMessage(),
                    'title' => $title,
                ]);
            }
        }

        return $this->generateHeuristicTeacherChallengeSuggestions($formattedBody, $title, $category);
    }

    /**
     * Merekomendasikan kunci jawaban esai langsung dari isi materi secara otomatis
     * saat guru menuliskan pertanyaan Tantangan Guru secara manual.
     *
     * @return array{recommended_answer: string, reference_excerpt: string, difficulty: string, difficulty_weight: int}
     */
    public function recommendAnswerForQuestion(
        string $body,
        string $title,
        string $question,
        ?string $category = null
    ): array {
        $cleanQuestion = trim($question);
        $formattedBody = $this->cleanText($body);

        if ($cleanQuestion === '') {
            return [
                'recommended_answer' => '',
                'reference_excerpt' => '',
                'difficulty' => 'medium',
                'difficulty_weight' => TeacherChallenge::weightForDifficulty('medium'),
            ];
        }

        if ($this->languageModel->isConfigured()) {
            try {
                $sampleText = $this->buildFullCoverageContext($formattedBody, 14000);
                $instructions = <<<INSTRUCTIONS
Kamu adalah Asisten Pendamping Guru SocratesRead AI.
Tugasmu: guru menuliskan satu pertanyaan esai secara manual. Baca seluruh materi yang diberikan dan susun REKOMENDASI KUNCI JAWABAN langsung yang paling akurat, jelas, dan lengkap untuk pertanyaan guru tersebut.

Keluaran WAJIB berupa JSON object valid:
{
  "recommended_answer": "Kunci jawaban model berparafrase rapi (1-3 kalimat) yang langsung menjawab pertanyaan guru secara akurat sesuai isi materi.",
  "reference_excerpt": "Kutipan kalimat asli terkuat dari materi yang menjadi bukti langsung jawaban tersebut.",
  "difficulty": "easy|medium|hard"
}

ATURAN:
1. Kunci jawaban HARUS murni berasal dari pembahasan isi materi, atau parafrase rapi dari materi tersebut (jaga istilah kunci seperti nama tokoh, pasal, perintah teknis, atau angka penting).
2. DILARANG mengarang fakta di luar materi. Apabila pertanyaan sedikit meluas, kaitkan dengan konsep inti materi yang paling relevan.
3. Keluarkan HANYA JSON object.
INSTRUCTIONS;

                $raw = $this->languageModel->respond([
                    [
                        'role' => 'user',
                        'content' => "Judul Materi: {$title}\nMata Pelajaran: {$category}\n\nPertanyaan Guru:\n{$cleanQuestion}\n\nIsi Materi Lengkap:\n{$sampleText}",
                    ],
                ], $instructions, [
                    'temperature' => 0.15,
                    'max_output_tokens' => 900,
                    'timeout' => 30,
                ]);

                $parsed = $this->decodeJsonObject($raw);
                if (is_array($parsed) && ! empty(trim((string) ($parsed['recommended_answer'] ?? '')))) {
                    $answer = trim((string) $parsed['recommended_answer']);
                    $excerpt = trim((string) ($parsed['reference_excerpt'] ?? ''));
                    $diff = strtolower(trim((string) ($parsed['difficulty'] ?? '')));
                    if (! in_array($diff, ['easy', 'medium', 'hard'], true)) {
                        $diff = $this->classifyDifficultyHeuristically([
                            'type' => 'essay',
                            'question' => $cleanQuestion,
                            'rubric' => $answer,
                        ]);
                    }

                    return [
                        'recommended_answer' => $answer,
                        'reference_excerpt' => $excerpt !== '' ? $excerpt : $this->findBestExcerptForQuestion($formattedBody, $title, $cleanQuestion, $answer),
                        'difficulty' => $diff,
                        'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
                    ];
                }
            } catch (Throwable $e) {
                Log::warning('AI manual question answer recommendation failed, using heuristic.', [
                    'error' => $e->getMessage(),
                    'title' => $title,
                ]);
            }
        }

        return $this->recommendHeuristicAnswerForQuestion($formattedBody, $title, $cleanQuestion);
    }

    /**
     * Pencari kutipan bukti tercepat (offline) dari materi untuk menyokong pertanyaan + kunci jawaban guru.
     */
    public function findBestExcerptForQuestion(string $body, string $title, string $question, string $rubric = ''): string
    {
        $combinedQuery = trim($question . ' ' . $rubric);
        if ($combinedQuery === '') {
            return '';
        }

        $result = $this->recommendHeuristicAnswerForQuestion($body, $title, $combinedQuery);

        return $result['reference_excerpt'];
    }

    /**
     * Menganalisis tingkat kesulitan (easy / medium / hard) soal yang dibuat guru beserta jawabannya.
     *
     * @param array<int, array<string, mixed>> $challenges
     * @return array<int, array<string, mixed>>
     */
    public function analyzeTeacherChallenges(string $body, string $title, array $challenges, bool $allowAi = true): array
    {
        if (empty($challenges)) {
            return [];
        }

        $needsAnalysisIdx = [];
        foreach ($challenges as $idx => $item) {
            $explicit = strtolower((string) ($item['difficulty'] ?? ''));
            if (! in_array($explicit, ['easy', 'medium', 'hard'], true) || ! empty($item['force_analyze'])) {
                $needsAnalysisIdx[] = $idx;
            }
        }

        if ($allowAi && ! empty($needsAnalysisIdx) && $this->languageModel->isConfigured()) {
            try {
                $sampleBody = $this->buildFullCoverageContext($body, 6000);
                $itemsPayload = [];
                foreach ($needsAnalysisIdx as $idx) {
                    $c = $challenges[$idx];
                    $answerInfo = ($c['type'] ?? 'essay') === 'multiple_choice'
                        ? ('Kunci PG: ' . ($c['correct_answer'] ?? '') . ' | Opsi: ' . json_encode($c['options'] ?? [], JSON_UNESCAPED_UNICODE))
                        : ('Kunci Jawaban/Rubrik: ' . ($c['rubric'] ?? '(belum diisi)'));
                    $itemsPayload[] = [
                        'index' => $idx,
                        'type' => $c['type'] ?? 'essay',
                        'question' => $c['question'] ?? '',
                        'answer_info' => $answerInfo,
                    ];
                }

                $instructions = <<<INSTRUCTIONS
Kamu adalah Asesor Kesulitan Soal SocratesRead AI.
Klasifikasikan level kesulitan setiap soal guru berikut berdasarkan kompleksitas berpikir Taksonomi Bloom, kedalaman materi, dan tuntutan jawaban/penalaran:
- "easy": Mengingat definisi dasar, fakta langsung, identifikasi tunggal (C1-C2).
- "medium": Membandingkan konsep, menjelaskan hubungan fungsi/sebab-akibat, analisis struktur (C3-C4).
- "hard": Evaluasi mendalam, studi kasus/dilema kompleks, sintesis beberapa konsep, perancangan solusi/strategi (C5-C6).

Keluarkan HANYA JSON array:
[{"index": 0, "difficulty": "medium"}]
INSTRUCTIONS;

                $raw = $this->languageModel->respond([
                    [
                        'role' => 'user',
                        'content' => "Judul Materi: {$title}\nRingkasan Materi:\n{$sampleBody}\n\nDaftar Soal Guru:\n" . json_encode($itemsPayload, JSON_UNESCAPED_UNICODE),
                    ],
                ], $instructions, [
                    'temperature' => 0.0,
                    'max_output_tokens' => 600,
                    'timeout' => 8,
                ]);

                $decoded = $this->decodeJsonArray($raw);
                if (is_array($decoded)) {
                    foreach ($decoded as $row) {
                        $i = $row['index'] ?? null;
                        $d = strtolower((string) ($row['difficulty'] ?? ''));
                        if (is_int($i) && isset($challenges[$i]) && in_array($d, ['easy', 'medium', 'hard'], true)) {
                            $challenges[$i]['difficulty'] = $d;
                            $challenges[$i]['difficulty_weight'] = TeacherChallenge::weightForDifficulty($d);
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('AI teacher challenge difficulty analysis failed, using heuristic.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Pastikan setiap item memiliki difficulty & difficulty_weight valid
        foreach ($challenges as $idx => $item) {
            $diff = strtolower((string) ($item['difficulty'] ?? ''));
            if (! in_array($diff, ['easy', 'medium', 'hard'], true)) {
                $diff = $this->classifyDifficultyHeuristically($item);
            }
            $challenges[$idx]['difficulty'] = $diff;
            $challenges[$idx]['difficulty_weight'] = TeacherChallenge::weightForDifficulty($diff);
        }

        return $challenges;
    }

    /**
     * Klasifikasi level kesulitan deterministik berdasarkan kata kerja kognitif, struktur soal, & kedalaman rubrik.
     *
     * @param array<string, mixed> $item
     */
    public function classifyDifficultyHeuristically(array $item): string
    {
        $q = mb_strtolower((string) ($item['question'] ?? ''));
        $rubric = mb_strtolower((string) ($item['rubric'] ?? ''));
        $type = (string) ($item['type'] ?? 'essay');
        $combined = $q . ' ' . $rubric;

        // Indikator level Hard (C5-C6: evaluasi, rancang, strategi, dilema, dampak jika tidak, sintesis)
        if (preg_match('/\b(evaluasi|kritik|rancang|strategi|solusi|dilema|studi kasus|bandingkan dan simpulkan|mengapa.*jika tidak|dampak jangka panjang|sintesis|buktikan|argumentasikan)\b/iu', $q)) {
            return 'hard';
        }
        // Soal multi-pertanyaan atau rubrik sangat kaya (>= 160 karakter dengan beberapa konsep)
        if ($type === 'essay' && (substr_count($q, '?') >= 2 || mb_strlen($rubric) >= 180)) {
            return 'hard';
        }

        // Indikator level Easy (C1-C2: apa yang dimaksud, sebutkan, siapa, kapan, pasal berapa, definisi)
        if (preg_match('/\b(apa yang dimaksud|apa pengertian|definisi|siapa|kapan|di mana|pasal berapa|sebutkan|kepanjangan)\b/iu', $q)
            && ! preg_match('/\b(mengapa|bagaimana|analisis|bandingkan|hubungkan|dampak|strategi)\b/iu', $q)) {
            return 'easy';
        }

        // Default Medium (analisis hubungan, fungsi, mekanisme, alasan)
        return 'medium';
    }

    /**
     * Memastikan bank soal kuis dokumen memiliki minimal $targetCount soal tanpa menimpa soal yang sudah ada.
     */
    public function ensureQuizQuestionsForDocument(Document $document, int $targetCount): void
    {
        $targetCount = min(100, max(5, $targetCount));
        $existingQuestions = $document->quizQuestions()->orderBy('sequence')->get();
        $existingCount = $existingQuestions->count();

        if ($existingCount >= $targetCount) {
            return;
        }

        $usedQuestionKeys = [];
        foreach ($existingQuestions as $eq) {
            $usedQuestionKeys[mb_strtolower(trim((string) $eq->question))] = true;
        }

        $needed = $targetCount - $existingCount;
        $nextSeq = (int) ($document->quizQuestions()->max('sequence') ?? 0) + 1;
        $toInsert = [];

        if ($this->languageModel->isConfigured()) {
            @set_time_limit(180);
            $batchTarget = min($needed + 5, 20);
            try {
                $aiItems = $this->generateQuizQuestionsWithAi(
                    $document->body,
                    $document->title,
                    $document->category,
                    $batchTarget,
                    45
                );
                if (is_array($aiItems)) {
                    foreach ($aiItems as $item) {
                        $qKey = mb_strtolower(trim((string) ($item['question'] ?? '')));
                        if ($qKey === '' || isset($usedQuestionKeys[$qKey])) {
                            continue;
                        }
                        $usedQuestionKeys[$qKey] = true;
                        $toInsert[] = $item;
                        if (count($toInsert) >= $needed) {
                            break;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Incremental AI quiz generation failed, using heuristic: ' . $e->getMessage());
            }
        }

        if (count($toInsert) < $needed) {
            $poolTarget = min(100, $targetCount + $existingCount + 10);
            $heuristics = $this->generateHeuristicQuizQuestions($document->body, $document->title, $document->category, $poolTarget);

            foreach ($heuristics as $item) {
                $qKey = mb_strtolower(trim((string) ($item['question'] ?? '')));
                if ($qKey === '' || isset($usedQuestionKeys[$qKey])) {
                    continue;
                }
                $usedQuestionKeys[$qKey] = true;
                $toInsert[] = $item;
                if (count($toInsert) >= $needed) {
                    break;
                }
            }
        }

        foreach ($toInsert as $offsetIdx => $qz) {
            $document->quizQuestions()->updateOrCreate(
                ['sequence' => $nextSeq + $offsetIdx],
                [
                    'question' => $qz['question'],
                    'options' => $qz['options'],
                    'correct_answer' => $qz['correct_answer'],
                    'explanation' => $qz['explanation'] ?? null,
                ]
            );
        }
    }

    /**
     * Menghasilkan soal kuis pilihan ganda yang cerdas, otomatis mencoba AI terlebih dahulu.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     */
    public function generateQuizQuestions(string $body, string $title, ?string $category = null, int $count = 10): array
    {
        $count = min(100, max(5, $count));

        if ($this->languageModel->isConfigured()) {
            @set_time_limit(180);
            try {
                $aiQuestions = $this->generateQuizQuestionsWithAi($body, $title, $category, $count);
                if (! empty($aiQuestions) && count($aiQuestions) >= min(5, $count)) {
                    return $aiQuestions;
                }
            } catch (Throwable $e) {
                Log::warning('AI quiz generation failed, using heuristic: ' . $e->getMessage());
            }
        }

        return $this->generateHeuristicQuizQuestions($body, $title, $category, $count);
    }

    /**
     * Menghasilkan soal pilihan ganda murni berbasis AI dari keseluruhan isi materi.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>|null
     */
    public function generateQuizQuestionsWithAi(string $body, string $title, ?string $category = null, int $count = 10, int $timeout = 60): ?array
    {
        $cleaned = $this->cleanText($body);
        $sampleText = $this->buildFullCoverageContext($cleaned, 18000);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Kurator Literasi & Perancang Soal Taksonomi Bloom (C1–C5) SocratesRead AI.
Baca KESELURUHAN isi materi dari paragraf awal hingga akhir lalu buat {$count} Soal Pilihan Ganda berjenjang Taksonomi Bloom C1 sampai C5 dalam format JSON array:
[
  {
    "bloom": "C4",
    "q": "Pertanyaan memakai kata kerja Bloom C1-C5 yang TIDAK menyalin paragraf utuh?",
    "a": "Pilihan A (ringkas & masuk akal)",
    "b": "Pilihan B (ringkas & masuk akal)",
    "c": "Pilihan C (ringkas & masuk akal)",
    "d": "Pilihan D (ringkas & masuk akal)",
    "k": "A",
    "e": "Penjelasan lengkap MENGAPA pilihan tersebut benar berdasarkan paragraf sumber yang menjawab pertanyaan."
  }
]
ATURAN WAJIB:
1. Wajib {$count} butir soal tersebar merata dari seluruh paragraf materi dan mencakup tingkat Bloom C1 sampai C5:
   - C1 Mengingat (identifikasi fakta/dasar hukum/konsep tepat),
   - C2 Memahami (jelaskan makna, fungsi, atau perbedaan inti),
   - C3 Menerapkan (pilih penerapan tepat pada situasi nyata),
   - C4 Menganalisis (analisis sebab-akibat, komponen, atau perbandingan),
   - C5 Mengevaluasi (minimal 2 soal: evaluasi ketepatan keputusan, kritisi kekeliruan, atau nilai dampak).
2. DILARANG menyalin paragraf utuh ke dalam soal maupun menaruh kalimat utuh panjang mentah sebagai opsi pilihan.
3. Field "e" (Pembahasan) HARUS menjelaskan alasan kunci jawaban benar berdasarkan paragraf yang benar-benar menjawab soal tersebut (BUKAN daftar tujuan pembelajaran, daftar pustaka, atau kutipan asal).
4. Keempat opsi (a, b, c, d) wajib unik, setipe (misal semua berupa pasal, semua berupa konsep/tindakan), serta seluruh pengecohnya masuk akal sesuai topik bacaan.
5. Keluarkan HANYA JSON array tanpa teks lain.
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nKategori: {$category}\n\nIsi Materi Lengkap:\n{$sampleText}",
            ],
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.15,
            'max_output_tokens' => max(3000, $count * 260),
            'timeout' => $timeout,
        ]);

        $data = $this->decodeJsonArray($rawResponse);
        if (! is_array($data) || empty($data)) {
            return null;
        }

        $results = [];
        foreach ($data as $idx => $item) {
            $seq = $idx + 1;
            $qText = trim((string) ($item['q'] ?? $item['question'] ?? ''));
            if ($qText === '') {
                continue;
            }

            $correctKey = strtoupper(trim((string) ($item['k'] ?? $item['correct_answer'] ?? 'A')));
            if (! in_array($correctKey, ['A', 'B', 'C', 'D'], true)) {
                $correctKey = 'A';
            }

            $rawItem = [
                'sequence' => $seq,
                'question' => $qText,
                'options' => [
                    ['key' => 'A', 'text' => (string) ($item['a'] ?? '')],
                    ['key' => 'B', 'text' => (string) ($item['b'] ?? '')],
                    ['key' => 'C', 'text' => (string) ($item['c'] ?? '')],
                    ['key' => 'D', 'text' => (string) ($item['d'] ?? '')],
                ],
                'correct_answer' => $correctKey,
                'explanation' => trim((string) ($item['e'] ?? $item['explanation'] ?? 'Sesuai dengan konsep yang dijelaskan dalam materi bacaan.')),
            ];
            $sanitized = $this->sanitizeQuizQuestionItem($rawItem, $cleaned);
            if ($sanitized === null) {
                continue;
            }
            $sanitized['sequence'] = count($results) + 1;
            $results[] = $sanitized;
            if (count($results) >= $count) {
                break;
            }
        }

        return ! empty($results) ? $results : null;
    }

    public function cleanText(string $rawText): string
    {
        return $this->formatter->format($rawText);
    }

    public function enhanceTypographyHeuristics(string $text): string
    {
        return $this->formatter->format($text);
    }

    /**
     * @return array{
     *     cleaned_body: string,
     *     excerpt: string,
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string, difficulty: string}>,
     *     quiz_questions: array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     * }
     */
    public function processWithHeuristics(string $rawText, string $title, ?string $category = null): array
    {
        $body = $this->cleanText($rawText);

        return [
            'cleaned_body' => $body,
            'excerpt' => $this->generateHeuristicExcerpt($body, $title),
            'questions' => $this->generateHeuristicSocraticQuestions($body, $title, $category),
            'teacher_challenges' => $this->generateHeuristicTeacherChallengeSuggestions($body, $title, $category),
            'quiz_questions' => $this->generateHeuristicQuizQuestions($body, $title, $category, 10),
        ];
    }

    public function generateHeuristicExcerpt(string $body, string $title): string
    {
        $titleLower = mb_strtolower(trim($title));
        $lines = preg_split('/\R/u', $body) ?: [];
        $cleanLines = [];
        $inCode = false;

        foreach ($lines as $rawLine) {
            $trimRaw = trim($rawLine);
            if (str_starts_with($trimRaw, '```')) {
                $inCode = ! $inCode;
                continue;
            }
            if ($inCode || $trimRaw === '') {
                continue;
            }
            // Abaikan heading Markdown (### ...), baris kapital semua, metadata cover (· / @), & tujuan pembelajaran generik
            if (str_starts_with($trimRaw, '#')) {
                continue;
            }
            $plain = trim(preg_replace('/[*_`]/u', '', strip_tags($trimRaw)) ?? '');
            $plain = trim(preg_replace('/^(?:[\-\•\*]|\d+[\.\)])\s+/u', '', $plain) ?? '');
            $plain = trim($plain, "\"“”' ");

            if ($plain === '' || mb_strlen($plain) < 35) {
                continue;
            }
            if (mb_strtolower($plain) === $titleLower) {
                continue;
            }
            if (preg_match('/\p{L}/u', $plain) && mb_strtoupper($plain) === $plain) {
                continue;
            }
            if (str_contains($plain, '·') || str_contains($plain, '@')) {
                continue;
            }
            // Abaikan baris judul/subjudul cover pendek yang tidak diakhiri titik
            if (! preg_match('/[.?!]$/u', $plain) && mb_strlen($plain) < 95) {
                continue;
            }
            if (preg_match('/^(?:setelah mengikuti|tujuan pembelajaran|lembar kerja|menjelaskan pengertian|mengidentifikasi kedudukan|menunjukkan sikap|menyusun strategi|sumber rujukan|daftar pustaka|terima kasih|proposal perancangan)/iu', $plain)) {
                continue;
            }

            $cleanLines[] = $plain;
        }

        if (empty($cleanLines)) {
            return "Pelajari konsep utama dan penerapan materi {$title} secara terstruktur untuk melatih pemahaman kritis.";
        }

        // Ambil kalimat utuh (tidak pernah terpotong di tengah kata)
        $joined = implode(' ', array_slice($cleanLines, 0, 4));
        $sentences = preg_split('/(?<=[.?!])\s+/u', $joined, -1, PREG_SPLIT_NO_EMPTY) ?: [$joined];
        $summary = '';
        foreach ($sentences as $sentence) {
            $s = trim($sentence);
            if ($s === '' || mb_strlen($s) < 25) {
                continue;
            }
            if ($summary === '') {
                $summary = $s;
            } elseif (mb_strlen($summary . ' ' . $s) <= 230) {
                $summary .= ' ' . $s;
            } else {
                break;
            }
            if (mb_strlen($summary) >= 130) {
                break;
            }
        }

        if ($summary === '') {
            $summary = $cleanLines[0];
        }

        // Jika masih terlalu panjang, potong rapi pada batas kata terakhir (bukan tengah kata)
        if (mb_strlen($summary) > 230) {
            $cut = mb_substr($summary, 0, 225);
            $lastSpace = mb_strrpos($cut, ' ');
            $summary = ($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut) . '…';
        }

        return $summary;
    }

    /**
     * @return array<int, array{type: string, question: string, context: string, rubric: string, reference_excerpt: string, difficulty: string, difficulty_weight: int}>
     */
    public function generateHeuristicTeacherChallengeSuggestions(string $body, string $title, ?string $category = null): array
    {
        $questions = $this->generateHeuristicQuestions($body, $title, $category);

        return array_map(function (array $q): array {
            $diff = $q['difficulty'] ?? 'medium';

            return [
                'type' => 'essay',
                'question' => $q['prompt'],
                'context' => null,
                'rubric' => $q['hint'],
                'reference_excerpt' => $q['reference_excerpt'] ?? $q['hint'],
                'difficulty' => $diff,
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
            ];
        }, $questions);
    }

    /**
     * @return array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string, reference_excerpt: string, difficulty: string}>
     */
    public function generateHeuristicSocraticQuestions(string $body, string $title, ?string $category = null): array
    {
        return $this->generateHeuristicQuestions($body, $title, $category, 'socratic');
    }

    /**
     * Membangun 5 soal esai berbasis bukti langsung (evidence-first) sehingga
     * setiap Pertanyaan, Kunci Jawaban, dan Rujukan Teks selalu konsisten dan saling cocok 100%.
     * Mode 'teacher' menghasilkan soal tantangan terstruktur; mode 'socratic' menghasilkan
     * pertanyaan penalaran Sokratik yang berbeda sudut pandang agar tidak pernah kembar.
     *
     * @return array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string, reference_excerpt: string, difficulty: string}>
     */
    public function generateHeuristicQuestions(string $body, string $title, ?string $category = null, string $mode = 'teacher'): array
    {
        $isSocratic = ($mode === 'socratic');
        $sentences = $this->extractSubstantiveSentences($body, $title);
        $kvBlocks = $this->extractKeyValuePairs($body);
        $total = count($sentences);
        $usedIndices = [];

        $isEditorShortcut = fn (string $s): bool => (bool) preg_match('/\b(?:ctrl\s*\+\s*x|tekan\s+y|tekan\s+enter)\b/iu', $s);
        $isTrivialExample = fn (string $s): bool => (bool) preg_match('/^Contoh\b/iu', trim($s))
            && count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []) < 10;

        $pickSentence = function (callable $matcher, int $fallbackIdx = 0, bool $allowEditorShortcut = false) use ($sentences, $total, &$usedIndices, $isEditorShortcut, $isTrivialExample): ?string {
            if ($total === 0) {
                return null;
            }
            foreach ($sentences as $idx => $s) {
                if (! isset($usedIndices[$idx]) && ! $isTrivialExample($s) && ($allowEditorShortcut || ! $isEditorShortcut($s)) && $matcher($s, $idx)) {
                    $usedIndices[$idx] = true;

                    return $s;
                }
            }
            for ($i = 0; $i < $total; $i++) {
                $idx = ($fallbackIdx + $i) % $total;
                if (! isset($usedIndices[$idx]) && ! $isTrivialExample($sentences[$idx]) && ($allowEditorShortcut || ! $isEditorShortcut($sentences[$idx]))) {
                    $usedIndices[$idx] = true;

                    return $sentences[$idx];
                }
            }
            for ($i = 0; $i < $total; $i++) {
                $idx = ($fallbackIdx + $i) % $total;
                if (! isset($usedIndices[$idx]) && ! $isTrivialExample($sentences[$idx])) {
                    $usedIndices[$idx] = true;

                    return $sentences[$idx];
                }
            }

            return $sentences[$fallbackIdx % $total];
        };

        $cleanSubject = function (string $subj): string {
            $s = trim($subj, " \t\n\r\0\x0B\"'“”‘’.,:;!?");
            $s = preg_replace('/^(?:pada|dalam|jika|ketika|saat|tentang|mengenai|bahwa)\s+/iu', '', $s) ?? $s;

            return mb_strlen($s) > 65 ? mb_substr($s, 0, 62) . '...' : $s;
        };

        $pickVariation = static function (string $seed, array $variants): string {
            $list = array_values(array_filter($variants, fn ($v) => is_string($v) && $v !== ''));
            if (empty($list)) {
                return '';
            }
            $hash = (int) sprintf('%u', crc32($seed));

            return $list[$hash % count($list)];
        };

        $q1Subject = '';

        // 1. Pengertian & Konsep Utama (Easy)
        $s1 = $pickSentence(
            fn (string $s, int $idx) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu|dengan demikian)\b/iu', $s)
                && ((preg_match('/\b(?:adalah|merupakan|yaitu|didefinisikan|memakai AI|berbasis|konsep)\b/iu', $s) && $idx <= 1) || $idx === ($isSocratic && $total > 6 ? 1 : 0)),
            $isSocratic && $total > 6 ? 1 : 0
        );
        $s1Follow = null;
        if ($total > 1 && $s1 !== null && mb_strlen($s1) < 160) {
            foreach ($sentences as $idx => $cand) {
                if ($cand !== $s1 && ! isset($usedIndices[$idx]) && ! $isEditorShortcut($cand) && preg_match('/\b(?:sehingga|menghemat|menumbuhkan|memadukan|tujuan|strategi)\b/iu', $cand)) {
                    $s1Follow = $cand;
                    $usedIndices[$idx] = true;
                    break;
                }
            }
        }
        if ($s1 !== null) {
            $q1Ref = rtrim($s1, '.') . '.' . ($s1Follow ? ' ' . rtrim($s1Follow, '.') . '.' : '');
            $q1Role = 'definition';
            $q1Subject = '';

            if (preg_match('/^(.{4,60}?)\s+(?:adalah|merupakan|yaitu|didefinisikan sebagai)\s+(.+)$/iu', $s1, $m) && ! preg_match('/^(?:contoh|kata)\b/iu', trim($m[1]))) {
                $subj = $cleanSubject($m[1]);
                $q1Subject = $subj;
                if ($isSocratic) {
                    $q1Prompt = "Mengapa gagasan \"{$subj}\" menjadi landasan penting dalam pembahasan ini, dan nilai inti apa yang dibawanya?";
                } else {
                    $hasPurpose = (bool) preg_match('/\b(?:tujuan(?:nya)?|untuk|guna|agar)\b/iu', $s1);
                    $q1Prompt = $pickVariation($title . '|q1|' . $subj, $hasPurpose ? [
                        "Mengapa penyusunan {$subj} menuntut pemenuhan kaidah intinya agar tujuan utamanya dapat tercapai secara utuh?",
                        "Analisis logika di balik aturan {$subj}, dan mengapa unsur pokoknya tidak boleh diabaikan saat mengolah bacaan!",
                        "Mengapa pendekatan {$subj} efektif mencapai tujuannya dibandingkan sekadar memotong isi bacaan secara acak?",
                    ] : [
                        "Mengapa konsep {$subj} menuntut keterpaduan seluruh unsur utamanya secara bersamaan, bukan mengandalkan satu aspek saja?",
                        "Analisis gagasan mendasar {$subj} dan mengapa pendekatan terpadu tersebut krusial bagi konteks yang dibahas!",
                        "Mengapa {$subj} memosisikan unsur-unsur pembentuknya agar saling melengkapi dalam kehidupan berbangsa maupun belajar?",
                    ]);
                }
            } elseif (
                preg_match('/\b(Sub-?Domain|VirtualHost|SocratesRead|Mangrove|Trigatra)\b/iu', $s1, $tm)
                && preg_match('/\b(?:menghemat|berfungsi|fungsi|tujuan|untuk|guna|agar|sehingga|memadukan|ditambahkan)\b/iu', $q1Ref)
            ) {
                $subj = $tm[1];
                $q1Subject = $subj;
                $q1Role = 'function_overview';
                $q1Prompt = $isSocratic
                    ? "Mengapa penggunaan {$subj} diperlukan dalam permasalahan yang diangkat materi ini? Jelaskan alasan logisnya!"
                    : "Mengapa fungsi dan gagasan utama penggunaan {$subj} dinilai lebih efisien daripada pendekatan konvensional dalam memecahkan kendala di materi?";
            } elseif (preg_match('/\b(Target\s+[^,.]{6,45}|Algoritma\s+RSA[^,.]{0,20}|Enkripsi\s+[^,.]{4,35}|Arsitektur\s+[^,.]{4,35})\b/iu', $s1, $tm2)) {
                $subj = trim(preg_replace('/\s+(?:yang|pada|di|dalam|untuk)$/iu', '', trim($tm2[1])) ?? trim($tm2[1]));
                $q1Subject = $subj;
                $q1Role = 'concept_summary';
                $q1Prompt = $isSocratic
                    ? "Mengapa gagasan mengenai \"{$subj}\" pada awal materi menjadi poin penting untuk diperhatikan?"
                    : $pickVariation($title . '|q1t|' . $subj, [
                        "Mengapa gagasan mengenai \"{$subj}\" menjadi poin kritis sejak awal materi, dan dampak apa yang dituju olehnya?",
                        "Analisis mengapa pembahasan tentang \"{$subj}\" krusial dipahami sebelum melangkah ke bagian materi berikutnya!",
                    ]);
            } else {
                $q1Prompt = $isSocratic
                    ? "Menurut penalaran Anda, mengapa gagasan pokok pada pembukaan materi \"{$title}\" krusial untuk dipahami?"
                    : $pickVariation($title . '|q1g', [
                        "Jelaskan konsep utama yang dipaparkan pada bagian awal materi \"{$title}\"!",
                        "Gagasan inti apa yang dibangun pada pembukaan materi \"{$title}\"?",
                    ]);
            }
            $q1Answer = $this->paraphraseAnswerFromSource($q1Ref, $q1Role, $title, $q1Prompt, $q1Subject);
            $q1Context = $isSocratic
                ? "Uji pemahamanmu dengan menjelaskan alasan logis serta bukti konsep dari paragraf pembuka."
                : "Fokuskan jawaban pada definisi, fungsi dasar, atau ide pokok yang langsung menjelaskan konsep awal materi.";
        } else {
            $q1Prompt = $isSocratic
                ? "Mengapa topik inti dalam materi \"{$title}\" relevan untuk dipahami secara menyeluruh?"
                : "Jelaskan konsep utama yang dibahas dalam materi \"{$title}\"!";
            $q1Answer = "Materi {$title} membahas konsep pokok dan penerapannya secara terstruktur.";
            $q1Ref = $q1Answer;
            $q1Context = "Uraikan gagasan inti dari materi.";
        }

        // Patokan nama topik yang jelas dari isi materi (pakai subjek konsep jika judul hanya singkatan yang cocok, mis. TBB -> Trigatra Bangun Bahasa)
        $cleanTitle = trim($title);
        $topicLabel = $cleanTitle;
        if (mb_strlen($cleanTitle) <= 6) {
            if ($q1Subject !== '' && preg_match_all('/\b(\p{L})/u', $q1Subject, $initials)) {
                $acronym = mb_strtoupper(implode('', $initials[1]));
                $topicLabel = str_starts_with($acronym, mb_strtoupper($cleanTitle))
                    ? $q1Subject
                    : 'bacaan ini';
            } else {
                $topicLabel = 'bacaan ini';
            }
        }

        // 2. Fungsi & Peranan Utama (Medium)
        $s2 = $pickSentence(
            fn (string $s) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu|dengan demikian|dan|atau)\b/iu', $s)
                && str_word_count($s) >= 7
                && preg_match('/\b(?:keluar\s+adalah\s+sama|membuat\s+virtualhost|fungsi(?:nya)?|peran(?:annya)?|tujuan(?:nya)?|kedudukan|menghemat|mengisi|mengganti|virtualhost|melindungi|bertujuan|dirancang|memastikan|meminimalkan|dampak(?:nya)?)\b/iu', $s),
            min($isSocratic && $total > 5 ? 2 : 1, max(0, $total - 1))
        );
        if ($s2 !== null) {
            $q2Role = 'function';
            $q2Subject = '';
            if (preg_match('/^(.{4,60}?)\s+(?:memiliki|berfungsi|berperan|bertujuan|adalah|merupakan|memerlukan|mengubah)\s+/iu', $s2, $m) && ! preg_match('/^(?:contoh|kata|jika|setelah|akan tetapi)\b/iu', trim($m[1]))) {
                $subj2 = $cleanSubject($m[1]);
                $q2Subject = $subj2;
                if ($isSocratic) {
                    $q2Prompt = "Apa dampak atau perubahan nyata yang dihasilkan oleh peran {$subj2} menurut uraian materi?";
                } elseif (preg_match('/\b(?:adalah|merupakan)\b/iu', $s2) && $q1Subject !== '' && strcasecmp($q1Subject, $subj2) !== 0) {
                    $q2Prompt = $pickVariation($title . '|q2c|' . $subj2, [
                        "Mengapa dalam menyusun {$subj2} penulis diperbolehkan mengubah urutan ide dari teks asli—berbeda dengan {$q1Subject}—dan batas kritis apa yang tetap harus dijaga?",
                        "Bandingkan pendekatan {$subj2} dengan {$q1Subject}: dalam kebutuhan belajar seperti apa {$subj2} jauh lebih tepat dipakai?",
                        "Analisis mengapa {$subj2} lebih menekankan penyeleksian inti gagasan lintas bagian dibanding mempertahankan alur {$q1Subject}!",
                    ]);
                } else {
                    $q2Prompt = $pickVariation($title . '|q2|' . $subj2, [
                        "Mengapa peran {$subj2} menjadi penentu efektivitas dalam materi ini, dan hambatan apa yang muncul bila fungsi tersebut diabaikan?",
                        "Evaluasi hubungan antara peran utama {$subj2} dan persoalan nyata yang dijawab dalam materi ini!",
                        "Bagaimana cara kerja {$subj2} menyelesaikan masalah utama materi dibanding jika tidak diterapkan?",
                    ]);
                }
            } elseif (preg_match('/\b(virtualhost|keluar\s+adalah\s+sama|sub-?domain|menumbuhkan|pelestarian|komunikasi|Agile\s+Scrum|purwarupa\s*\(MVP\)|MVP)\b/iu', $s2, $tm)) {
                $topic2 = stripos($tm[1], 'keluar') !== false || stripos($s2, 'virtualhost') !== false ? 'VirtualHost' : $tm[1];
                $q2Subject = $topic2;
                $q2Role = 'reason';
                $q2Prompt = $isSocratic
                    ? "Bagaimana solusi terkait \"{$topic2}\" membuktikan efektivitasnya dalam menyelesaikan kendala yang dibahas?"
                    : "Mengapa mekanisme terkait \"{$topic2}\" diperlukan, dan masalah apa yang muncul apabila langkah tersebut dilewati?";
            } elseif (preg_match('/^(Kedudukan|Fungsi|Peran|Landasan|Syarat|Prinsip)\s*:\s*(.+)$/iu', $s2, $lbl)) {
                $lblText = mb_strtolower($lbl[1]);
                $lblBody = rtrim(trim(str_replace(' & ', ' dan ', $lbl[2])), '.');
                $lblBodyClean = trim(preg_replace('/\s*\([^)]*\)/u', '', $lblBody) ?? $lblBody);
                $lblAnchor = preg_match('/\b(?:Pasal\s*36|bahasa\s+nasional)\b/iu', $s2)
                    ? 'Bahasa Indonesia'
                    : (preg_match('/\b(?:Pasal\s*32|kebudayaan\s+nasional)\b/iu', $s2)
                        ? 'Bahasa Daerah'
                        : $topicLabel);
                $q2Subject = $lblAnchor;
                $q2Role = 'reason';
                $q2Prompt = $isSocratic
                    ? "Mengapa {$lblAnchor} memiliki {$lblText} sebagai {$lblBodyClean} dalam {$topicLabel}, dan apa konsekuensinya bila diabaikan?"
                    : $pickVariation($topicLabel . '|q2lbl|' . $lblText, [
                        "Mengapa {$lblAnchor} harus memiliki {$lblText} resmi sebagai {$lblBodyClean} dalam kerangka {$topicLabel}, dan ketimpangan apa yang terjadi di ruang publik atau sekolah jika posisi ini tergeser?",
                        "Analisis pentingnya {$lblText} {$lblAnchor} ({$lblBodyClean}) dalam {$topicLabel}, serta bagaimana ketegasan aturan tersebut mengarahkan sikap berbahasa di lingkungan sekitar!",
                    ]);
            } elseif (preg_match('/\bTujuan\s*:\s*/iu', $s2)) {
                $q2Role = 'purpose';
                $q2Prompt = $isSocratic
                    ? "Mengapa tujuan utama perancangan dalam materi \"{$topicLabel}\" penting untuk dicapai?"
                    : "Mengapa tujuan utama dalam pembahasan {$topicLabel} menuntut pendekatan tersebut, dan dampak nyata apa yang diberikannya bagi pengguna?";
            } else {
                $q2Prompt = $isSocratic
                    ? "Bukti dan alasan apa yang menunjukkan bahwa mekanisme pada {$topicLabel} berhasil menjawab permasalahan?"
                    : $pickVariation($topicLabel . '|q2g|' . substr($s2, 0, 20), [
                        "Analisis alasan di balik pendekatan yang dijelaskan dalam {$topicLabel}, serta konsekuensi nyatanya bila tidak diterapkan secara tepat!",
                        "Mengapa mekanisme pada bagian ini krusial dalam memahami persoalan yang dibahas pada {$topicLabel}?",
                    ]);
            }
            $q2Ref = rtrim($s2, '.') . '.';
            $q2Answer = $this->paraphraseAnswerFromSource($q2Ref, $q2Role, $title, $q2Prompt, $q2Subject);
            $q2Context = $isSocratic
                ? "Tunjukkan alasan rasional beserta dampaknya apabila fungsi ini dijalankan atau diabaikan."
                : "Jelaskan alasan logis, fungsi spesifik, atau solusi yang dijelaskan pada bagian tersebut.";
        } else {
            $q2Prompt = $isSocratic
                ? "Apa konsekuensinya jika peran utama dalam materi \"{$title}\" tidak tercapai?"
                : "Apa fungsi dan peranan utama dari topik dalam materi \"{$title}\"?";
            $q2Answer = $q1Answer;
            $q2Ref = $q1Ref;
            $q2Context = "Uraikan fungsi utama yang dibahas pada materi.";
        }

        // 3. Karakteristik & Komponen Kunci (Medium)
        if (! empty($kvBlocks) && ! $isSocratic) {
            $sampleKeys = array_slice(array_keys($kvBlocks), 0, 3);
            $keysLabel = implode(', ', $sampleKeys);
            $q3Prompt = "Jelaskan peran dan fungsi masing-masing komponen berikut dalam konfigurasi materi: {$keysLabel}!";
            $kvSummaries = [];
            foreach ($kvBlocks as $k => $v) {
                $kvSummaries[] = "{$k} berperan sebagai {$v}";
            }
            $q3Answer = 'Masing-masing komponen memiliki peran spesifik yang saling melengkapi, yaitu ' . implode('; ', $kvSummaries) . '.';
            $q3Ref = implode(' · ', array_map(fn ($k, $v) => "{$k} = {$v}", array_keys($kvBlocks), $kvBlocks));
            $q3Context = "Sebutkan perbedaan fungsi tiap komponen atau parameter tersebut dengan kata-kata sendiri.";
        } else {
            $isNearDupQ1 = fn (string $s): bool => $q1Subject !== ''
                && (bool) preg_match('/^' . preg_quote($q1Subject, '/') . '\s+(?:adalah|merupakan)\b/iu', trim($s))
                && ! preg_match('/\b(?:pasal|uud|sites-availeble|a2ensite|apache|enkripsi|RSA)\b/iu', $s);
            $s3 = $pickSentence(
                fn (string $s) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu)\b/iu', $s)
                    && ! $isNearDupQ1($s)
                    && str_word_count($s) >= 7
                    && preg_match('/\b(?:komponen|aspek|unsur|pasal|uud|kebudayaan\s+nasional|dilindungi|terdiri|mencakup|spesifikasi|algoritma|modul|standar|arsitektur|konfigurasi|sites-availeble|a2ensite|apache|struktur|subjektif|objektif|perbedaan|tafsiran)\b/iu', $s),
                min($isSocratic && $total > 5 ? 3 : 2, max(0, $total - 1))
            );
            if ($s3 !== null) {
                $q3Subject = '';
                $q3Role = 'component';
                $isTechSentence = (bool) preg_match('/\b(?:apache|virtualhost|sites-availeble|a2ensite|enkripsi|RSA|algoritma|modul|server|jaringan|basis\s+data|protokol|konfigurasi|komputasi)\b/iu', $s3);
                $isLegalSentence = (bool) preg_match('/\b(?:pasal|uud|undang|hukum|kebijakan|negara|dilindungi|kebudayaan\s+nasional)\b/iu', $s3);
                if (preg_match('/^(Kedudukan|Landasan|Dasar|Fungsi|Peran)\s*:\s*(.+)$/iu', $s3, $lbl3)) {
                    $q3Anchor = preg_match('/\b(?:Pasal\s*32|kebudayaan\s+nasional)\b/iu', $s3)
                        ? 'Bahasa Daerah'
                        : (preg_match('/\b(?:Pasal\s*36|bahasa\s+nasional)\b/iu', $s3)
                            ? 'Bahasa Indonesia'
                            : $topicLabel);
                    $q3Subject = $q3Anchor;
                    $q3Role = 'reason';
                    $q3Prompt = $isSocratic
                        ? "Mengapa pelindungan terhadap {$q3Anchor} menjadi penentu keseimbangan budaya dalam {$topicLabel}?"
                        : $pickVariation($topicLabel . '|q3lbl|' . $q3Anchor, [
                            "Mengapa pelindungan konstitusional terhadap {$q3Anchor} dalam {$topicLabel} menjadi hal mendesak, dan ancaman sosial-budaya apa yang timbul apabila generasi muda meninggalkannya?",
                            "Evaluasi mengapa {$q3Anchor} harus dijamin posisinya oleh aturan dalam {$topicLabel}, serta peran kritisnya bagi keberagaman di lingkungan masyarakat!",
                        ]);
                } elseif (preg_match('/^(.{4,60}?)\s+(?:memerlukan|mencakup|terdiri|dilindungi|berfungsi|adalah|merupakan)\s+/iu', $s3, $m) && ! preg_match('/^(?:contoh|kata|jika|setelah)\b/iu', trim($m[1]))) {
                    $subj3 = $cleanSubject($m[1]);
                    $q3Subject = $subj3;
                    if ($isSocratic) {
                        $q3Prompt = "Mengapa {$subj3} membutuhkan elemen atau spesifikasi tersebut agar sistem berjalan selaras?";
                    } elseif ($isTechSentence) {
                        $q3Prompt = $pickVariation($title . '|q3t|' . $subj3, [
                            "Mengapa {$subj3} mewajibkan aturan atau spesifikasi teknis tersebut, dan kegagalan sistem apa yang terjadi jika tidak dipenuhi?",
                            "Analisis bagaimana keterkaitan komponen pada {$subj3} menjamin kestabilan dan ketepatan hasil pada sistem!",
                        ]);
                    } elseif ($isLegalSentence) {
                        $q3Prompt = $pickVariation($topicLabel . '|q3l|' . $subj3, [
                            "Mengapa pelindungan aturan terhadap {$subj3} dalam {$topicLabel} menjadi hal mendesak, dan dampak sosial-budaya apa yang terjadi bila masyarakat mengabaikannya?",
                            "Evaluasi bagaimana ketentuan dasar yang mengatur {$subj3} menguji kesadaran warga untuk menjaga keseimbangan antara identitas lokal dan tuntutan zaman!",
                        ]);
                    } elseif ($q1Subject !== '' && strcasecmp($q1Subject, $subj3) === 0) {
                        $q3Prompt = $pickVariation($topicLabel . '|q3s|' . $subj3, [
                            "Mengapa pilar-pilar kunci dalam {$subj3} wajib dijalankan secara seimbang, dan apa risiko ketimpangannya bagi masyarakat bila salah satu pilar terabaikan?",
                            "Evaluasi keterkaitan antarunsur dalam {$subj3}: mengapa hilangnya satu pilar dapat mengganggu keutuhan sistem yang diharapkan?",
                        ]);
                    } else {
                        $baseRef = $q1Subject !== '' ? $q1Subject : $topicLabel;
                        $q3Prompt = $pickVariation($topicLabel . '|q3n|' . $subj3, [
                            "Berbeda dari penyajian objektif seperti {$baseRef}, mengapa {$subj3} justru mengizinkan sudut pandang dan tafsiran pribadi penulis—dan batas kritis apa yang harus dijaga agar tetap bertanggung jawab?",
                            "Evaluasi kelebihan sekaligus tantangan penggunaan {$subj3} yang bebas struktur: dalam situasi tugas belajar seperti apa bentuk subjektif ini jauh lebih relevan dipakai?",
                            "Bagaimana kelonggaran struktur dan ruang tafsiran pada {$subj3} menuntut siswa lebih kritis menilai kualitas sebuah bacaan di sekolah?",
                        ]);
                    }
                } elseif (preg_match('/\b(virtualhost|apache|sites-availeble|a2ensite|portal|mail\.yahoo\.com|pasal \d+|RSA \d+-bit)\b/iu', $s3, $tm3)) {
                    $q3Subject = $tm3[1];
                    $q3Prompt = $isSocratic
                        ? "Apa signifikansi teknis dari pengaturan \"{$tm3[1]}\" terhadap kesesuaian hasil konfigurasi dalam materi?"
                        : "Mengapa pengaturan atau ketentuan terkait \"{$tm3[1]}\" krusial dijalankan dengan tepat, dan apa akibatnya bagi keseluruhan sistem jika keliru?";
                } else {
                    $q3Prompt = $isSocratic
                        ? "Simpulan apa yang dapat ditarik mengenai keterkaitan antarunsur atau aturan utama dalam materi \"{$title}\"?"
                        : $pickVariation($title . '|q3g', [
                            "Ciri dan batasan kritis apa yang membedakan pembahasan utama dalam materi \"{$topicLabel}\", serta bagaimana implikasinya saat diterapkan?",
                            "Analisis mengapa unsur-unsur kunci pada \"{$topicLabel}\" saling bergantung, bukan berdiri sendiri!",
                        ]);
                }
                $q3Ref = rtrim($s3, '.') . '.';
                $q3Answer = $this->paraphraseAnswerFromSource($q3Ref, $q3Role, $title, $q3Prompt, $q3Subject);
                $q3Context = $isSocratic
                    ? "Bandingkan atau simpulkan hubungan antarkomponen berdasarkan bukti bacaan."
                    : "Identifikasi elemen, aturan hukum/pasal, atau spesifikasi teknis yang mendukungnya.";
            } else {
                $q3Prompt = $isSocratic
                    ? "Bagaimana unsur-unsur dalam materi \"{$title}\" saling bergantung satu sama lain?"
                    : "Karakteristik dan komponen penting apa saja yang menyusun materi \"{$title}\"?";
                $q3Answer = $q1Answer;
                $q3Ref = $q1Ref;
                $q3Context = "Uraikan bagian-bagian penting dari bacaan.";
            }
        }

        // 4. Cara Kerja & Mekanisme / Prosedur / Dampak (Hard)
        $s4 = $pickSentence(
            fn (string $s, int $idx) => $idx >= intdiv($total, 4)
                && str_word_count($s) >= 8
                && ! preg_match('/^(?:sehingga|contoh)\b/iu', $s)
                && preg_match('/\b(?:perintah|langkah|pertama|aktifkan|salin|edit|prinsip|mengutamakan|melestarikan|menguasai|mekanisme|pengujian|mengakibatkan|memicu|menjamin|menghindari|gamifikasi|alokasi|kapasitas|pemetaan|dasbor|anti-mencontek|dampak|perkembangan|penyalahgunaan|kecanduan|keseimbangan|berpengaruh)\b/iu', $s),
            min(max(0, intdiv($total, 2) + ($isSocratic ? 1 : 0)), max(0, $total - 1)),
            false
        );
        if ($s4 !== null) {
            $q4Role = 'procedure';
            if (preg_match('/\b(perintah|konfigurasi|folder|aktifkan|a2ensite|apache|service|salin)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Mengapa urutan eksekusi atau langkah konfigurasi pada tahap ini krusial, dan risiko apa jika dilewati?"
                    : "Mengapa urutan langkah konfigurasi atau perintah pada tahap ini wajib dijalankan secara tepat, dan gangguan apa yang muncul jika dilewati?";
            } elseif (preg_match('/\b(prinsip|mengutamakan|melestarikan|menguasai)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Apa risiko ketimpangan yang terjadi apabila prinsip kerja tersebut diabaikan?"
                    : $pickVariation($topicLabel . '|q4pr|' . substr($s4, 0, 20), [
                        "Jika di lingkungan sekolah atau media sosial penggunaan bahasa asing mulai menggeser Bahasa Indonesia dan bahasa daerah, bagaimana penerapan prinsip {$topicLabel} seharusnya memulihkan keseimbangan tersebut?",
                        "Mengapa prinsip {$topicLabel} menuntut ketiga pilar dijalankan beriringan alih-alih memilih salah satu saja, dan dampak kritis apa yang muncul jika keseimbangannya timpang?",
                    ]);
            } elseif (preg_match('/\b(alokasi|kapasitas|penyimpanan|terabita|kuota|ruang)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Berdasarkan fakta kapasitas dan sumber daya pada bacaan, keputusan evaluasi apa yang perlu diambil?"
                    : "Evaluasi kondisi alokasi kapasitas atau sumber daya yang dipaparkan pada materi, serta konsekuensinya jika melebihi batas yang tersedia!";
            } elseif (preg_match('/\b(permainan|gamifikasi|boss battle|anti-mencontek|dasbor)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Mengapa mekanisme gamifikasi atau kontrol kualitas tersebut efektif menjaga keterlibatan dan kejujuran belajar?"
                    : "Mengapa mekanisme pendukung (seperti gamifikasi atau kontrol kejujuran belajar) dirancang demikian untuk mengubah cara siswa memahami bacaan?";
            } elseif (preg_match('/\b(dampak|perkembangan|teknologi|penyalahgunaan|kecanduan|kemudahan)\b/iu', $s4)) {
                $q4Role = 'cause_effect';
                $hasNegativeImpact = (bool) preg_match('/\b(risiko|negatif|penyalahgunaan|kecanduan|buruk|bahaya)\b/iu', $s4);
                $q4Prompt = $isSocratic
                    ? "Mengapa perkembangan tersebut memunculkan dampak nyata bagi pengguna, dan bagaimana analisis sebab-akibatnya?"
                    : ($hasNegativeImpact
                        ? $pickVariation($topicLabel . '|q4dn|' . substr($s4, 0, 20), [
                            "Analisis hubungan sebab-akibat antara kemudahan akses dan penyalahgunaan yang terjadi di lingkungan digital, serta mengapa kendali diri sangat menentukan!",
                            "Mengapa dampak negatif perkembangan tersebut mudah menjebak pengguna muda, dan langkah pencegahan kritis apa yang perlu dibiasakan?",
                        ])
                        : $pickVariation($topicLabel . '|q4dp|' . substr($s4, 0, 20), [
                            "Mengapa kemudahan komunikasi dan akses informasi dari kemajuan teknologi informasi tetap menuntut daya pilih kritis siswa agar tidak sekadar menerima informasi mentah?",
                            "Analisis mengapa dampak positif akses informasi pada contoh bacaan harus diimbangi sikap selektif dalam kegiatan belajar di masyarakat!",
                        ]));
            } else {
                $q4Prompt = $isSocratic
                    ? "Analisis hubungan sebab-akibat yang terjadi pada mekanisme inti dalam materi \"{$topicLabel}\" beserta implikasi kritisnya!"
                    : $pickVariation($topicLabel . '|q4g', [
                        "Analisis hubungan sebab-akibat pada mekanisme utama {$topicLabel}, serta konsekuensi nyata yang muncul apabila satu tahap terganggu!",
                        "Mengapa keterkaitan faktor pada {$topicLabel} menghasilkan dampak tersebut dalam situasi nyata?",
                    ]);
            }
            $q4Ref = rtrim($s4, '.') . '.';
            $q4Answer = $this->paraphraseAnswerFromSource($q4Ref, $q4Role, $title, $q4Prompt);
            $q4Context = $isSocratic
                ? "Uji argumen sebab-akibat dan jelaskan konsekuensinya secara runtut."
                : "Jelaskan urutan proses, aturan operasional, atau hubungan sebab-akibat secara runtut.";
        } else {
            $q4Prompt = $isSocratic
                ? "Mengapa ketelitian prosedural pada materi \"{$title}\" menentukan keberhasilan hasilnya?"
                : "Mengapa alur penerapan konsep dalam materi \"{$topicLabel}\" harus dijalankan secara runtut agar hasilnya akurat?";
            $q4Answer = $q2Answer;
            $q4Ref = $q2Ref;
            $q4Context = "Uraikan alur mekanisme atau sebab-akibatnya.";
        }

        // 5. Manfaat, Troubleshooting, & Penerapan Praktis (Hard)
        $s5 = $pickSentence(
            fn (string $s, int $idx) => $idx >= intdiv($total * 2, 3)
                && preg_match('/\b(?:jika tidak|restart|tekan f5|periksa|contoh penggunaan|era digital|strategi|menjaga|meningkatkan|efisiensi|latensi|penerapan|indeks|mengubah AI|pelatih berpikir|kesadaran|tanggung jawab|berpengaruh|manfaat|kehidupan|moral|etika)\b/iu', $s),
            max(0, $total - ($isSocratic && $total > 2 ? 2 : 1))
        );
        if ($s5 !== null) {
            if (preg_match('/\b(jika tidak bisa|restart|tekan f5|periksa konfigurasi)\b/iu', $s5)) {
                $q5Prompt = $isSocratic
                    ? "Evaluasi alur diagnostik terbaik yang paling masuk akal berdasarkan materi jika layanan tidak menampilkan perubahan baru!"
                    : "Evaluasi langkah diagnostik mana yang harus didahulukan ketika konfigurasi sudah disimpan tetapi tampilan layanan belum berubah saat diuji di browser!";
                $q5Role = 'troubleshooting';
            } elseif (preg_match('/\b(kesadaran|tanggung jawab|moral|etika)\b/iu', $s5)) {
                $q5Prompt = $isSocratic
                    ? "Evaluasi mengapa refleksi kesadaran etis menjadi penentu keberhasilan penerapan materi ini di kehidupan nyata!"
                    : $pickVariation($topicLabel . '|q5m|' . substr($s5, 0, 20), [
                        "Evaluasi mengapa kemudahan akses informasi tanpa kesadaran dan tanggung jawab moral justru dapat merugikan lingkungan belajar maupun kehidupan bermasyarakat!",
                        "Evaluasi alasan logis mengapa pemanfaatan teknologi informasi tetap bergantung pada kesadaran etis serta tanggung jawab moral penggunanya!",
                    ]);
                $q5Role = 'application';
            } elseif (preg_match('/\b(era digital|strategi|menjaga|rusak|penerapan|contoh penggunaan)\b/iu', $s5)) {
                $q5Prompt = $isSocratic
                    ? "Evaluasi strategi yang paling efektif untuk menjaga keutuhan konsep materi ini di tengah tantangan praktik masa kini!"
                    : $pickVariation($topicLabel . '|q5a|' . substr($s5, 0, 20), [
                        "Evaluasi tantangan nyata di era digital apabila siswa tidak menguasai kemampuan dalam {$topicLabel} secara kritis untuk menjaga identitas lokal dan nasional!",
                        "Evaluasi dampak kritis di lingkungan pendidikan maupun pergaulan global jika generasi muda di era digital abai terhadap tuntutan penguasaan dalam {$topicLabel}!",
                    ]);
                $q5Role = 'application';
            } else {
                $q5Prompt = $isSocratic
                    ? "Evaluasi simpulan dan penerapan praktis paling relevan dari akhir materi \"{$title}\" untuk konteks nyata!"
                    : $pickVariation($title . '|q5c', [
                        "Evaluasi dampak jangka panjang yang muncul apabila simpulan utama dari {$topicLabel} diterapkan atau justru diabaikan di lingkungan nyata!",
                        "Evaluasi implikasi kritis dari bagian akhir {$topicLabel} terhadap pengambilan keputusan pengguna di keseharian!",
                    ]);
                $q5Role = 'conclusion';
            }
            $q5Ref = rtrim($s5, '.') . '.';
            $q5Answer = $this->paraphraseAnswerFromSource($q5Ref, $q5Role, $title, $q5Prompt);
            $q5Context = $isSocratic
                ? "Sertakan alasan reflektif atau solusi praktis yang berpijak pada fakta akhir bacaan."
                : "Berikan penjelasan solusi konkret atau contoh penerapan yang selaras dengan fakta pada akhir materi.";
        } else {
            $q5Prompt = $isSocratic
                ? "Evaluasi manfaat utama materi \"{$title}\" apabila diterapkan pada situasi nyata!"
                : "Evaluasi penerapan nyata serta manfaat utama materi \"{$topicLabel}\" dalam praktik!";
            $q5Answer = $q2Answer;
            $q5Ref = $q2Ref;
            $q5Context = "Rancang penerapan konkret berdasarkan isi bacaan.";
        }

        return [
            [
                'sequence' => 1,
                'skill' => 'Pengertian & Konsep Utama',
                'bloom_level' => 'C2',
                'prompt' => $q1Prompt,
                'context' => $q1Context,
                'hint' => $q1Answer,
                'reference_excerpt' => $q1Ref,
                'difficulty' => 'easy',
            ],
            [
                'sequence' => 2,
                'skill' => 'Fungsi & Peranan Utama',
                'bloom_level' => 'C3',
                'prompt' => $q2Prompt,
                'context' => $q2Context,
                'hint' => $q2Answer,
                'reference_excerpt' => $q2Ref,
                'difficulty' => 'medium',
            ],
            [
                'sequence' => 3,
                'skill' => 'Karakteristik & Komponen Kunci',
                'bloom_level' => 'C4',
                'prompt' => $q3Prompt,
                'context' => $q3Context,
                'hint' => $q3Answer,
                'reference_excerpt' => $q3Ref,
                'difficulty' => 'medium',
            ],
            [
                'sequence' => 4,
                'skill' => 'Cara Kerja & Mekanisme',
                'bloom_level' => 'C4',
                'prompt' => $q4Prompt,
                'context' => $q4Context,
                'hint' => $q4Answer,
                'reference_excerpt' => $q4Ref,
                'difficulty' => 'hard',
            ],
            [
                'sequence' => 5,
                'skill' => 'Manfaat & Penerapan Praktis',
                'bloom_level' => 'C5',
                'prompt' => $q5Prompt,
                'context' => $q5Context,
                'hint' => $q5Answer,
                'reference_excerpt' => $q5Ref,
                'difficulty' => 'hard',
            ],
        ];
    }

    /**
     * Algoritma pendamping offline untuk menyarankan kunci jawaban esai paling sesuai
     * saat guru mengetik pertanyaan manual, memakai penyamaan kata utuh + paragraf sumber + intent.
     *
     * @return array{recommended_answer: string, reference_excerpt: string, difficulty: string, difficulty_weight: int}
     */
    private function recommendHeuristicAnswerForQuestion(string $body, string $title, string $question): array
    {
        $cleanQuestion = trim($question);
        $sentences = $this->extractSubstantiveSentences($body, $title);
        $blocks = $this->extractSectionBlocksForSearch($body, $title);
        $kvBlocks = $this->extractKeyValuePairs($body);
        $paragraphs = array_values(array_filter(
            preg_split('/\R{2,}/u', trim($body)) ?: [],
            fn (string $p): bool => mb_strlen(trim($p)) >= 25
        ));

        // Deteksi intent pertanyaan
        $qLower = mb_strtolower($cleanQuestion);
        $intent = 'general';
        if (preg_match('/\b(?:pengertian|apa yang dimaksud|definisi|arti|konsep|maksud dari|siapa|kapan|di mana)\b/iu', $cleanQuestion)) {
            $intent = 'definition';
        } elseif (preg_match('/\b(?:mengapa|kenapa|sebab|alasan|dampak|akibat|evaluasi|nilai)\b/iu', $cleanQuestion)) {
            $intent = 'reason';
        } elseif (preg_match('/\b(?:fungsi|peran|manfaat|kegunaan|kedudukan|tujuan)\b/iu', $cleanQuestion)) {
            $intent = 'function';
        } elseif (preg_match('/\b(?:bagaimana|langkah|cara|prosedur|perintah|strategi|prinsip|terapkan)\b/iu', $cleanQuestion)) {
            $intent = 'procedure';
        } elseif (preg_match('/\b(?:contoh|sebutkan|apa saja|macam|jenis|aspek|komponen|unsur)\b/iu', $cleanQuestion)) {
            $intent = 'example';
        }

        // Bersihkan frasa tanya umum agar fokus pencarian menyasar konsep inti yang ditanya guru
        $focusQuery = preg_replace('/\b(?:yang\s+(?:kamu|anda|kalian)\s+ketahui|menurut\s+(?:kamu|anda|pendapatmu|materi)|sejauh\s+pemahamanmu|coba\s+jelaskan|jelaskan(?:\s+secara\s+singkat)?|evaluasi|analisis|bandingkan|terapkan|apa\s+yang\s+dimaksud(?:\s+dengan)?|apa\s+pengertian(?:\s+dari)?|apakah\s+pengertian|apa\s+fungsi(?:\s+dari)?|mengapa|kenapa|bagaimana|sebutkan|uraikan)\b/iu', ' ', $cleanQuestion) ?? $cleanQuestion;
        $focusQuery = trim(preg_replace('/[?.,!]+/u', ' ', $focusQuery) ?? '');
        if (mb_strlen($focusQuery) < 3) {
            $focusQuery = $cleanQuestion;
        }

        $qKeywords = $this->similarity->extractKeywords($focusQuery);
        $rawTerms = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($focusQuery)) ?: [],
            fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, ['yang', 'dan', 'dari', 'pada', 'atau', 'dengan', 'untuk', 'dalam', 'tentang', 'apa', 'apakah'], true)
        )));

        $titleTerms = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)) ?: [],
            fn (string $w) => mb_strlen($w) >= 3
        )));
        $specificTerms = array_values(array_diff($rawTerms, $titleTerms));

        // Evaluasi skor relevansi setiap paragraf agar kalimat dari paragraf yang benar-benar menjawab mendapat prioritas tinggi
        $bestParagraphText = '';
        $bestParaScore = 0.0;
        foreach ($paragraphs as $para) {
            $pScore = 0.0;
            $pConceptMap = $this->similarity->expandConceptMap($this->similarity->extractKeywords($para), $para);
            foreach ($specificTerms as $term) {
                if ($this->containsWholeWordTerm($para, $term) || $this->similarity->tokenMatchesConceptMap($term, $pConceptMap, $para)) {
                    $pScore += 3.0;
                }
            }
            foreach ($qKeywords as $kw) {
                if ($this->similarity->tokenMatchesConceptMap($kw, $pConceptMap, $para)) {
                    $pScore += in_array($kw, $titleTerms, true) ? 1.0 : 2.0;
                }
            }
            if ($pScore > $bestParaScore) {
                $bestParaScore = $pScore;
                $bestParagraphText = $para;
            }
        }

        // Gabungkan kandidat kalimat & blok terstruktur
        $candidates = [];
        foreach ($sentences as $idx => $s) {
            $candidates[] = ['text' => $s, 'kind' => 'sentence', 'order' => $idx];
        }
        foreach ($blocks as $bIdx => $blockText) {
            $candidates[] = ['text' => $blockText, 'kind' => 'block', 'order' => $bIdx];
        }
        if (! empty($kvBlocks) && count($kvBlocks) >= 2) {
            $kvSummary = implode('; ', array_map(fn ($k, $v) => "{$k} berperan sebagai {$v}", array_keys($kvBlocks), $kvBlocks));
            $candidates[] = ['text' => $kvSummary, 'kind' => 'kv', 'order' => 999];
        }

        $scored = [];
        $asksAboutExit = $this->containsWholeWordTerm($cleanQuestion, 'ctrl')
            || $this->containsWholeWordTerm($cleanQuestion, 'keluar')
            || $this->containsWholeWordTerm($cleanQuestion, 'simpan');

        foreach ($candidates as $cand) {
            $text = $cand['text'];
            $conceptMap = $this->similarity->expandConceptMap($this->similarity->extractKeywords($text), $text);

            $score = 0.0;
            // Cocokkan istilah spesifik (non-judul) per KATA UTUH agar pencarian akurat
            foreach ($specificTerms as $term) {
                if ($this->containsWholeWordTerm($text, $term) || $this->similarity->tokenMatchesConceptMap($term, $conceptMap, $text)) {
                    $score += 3.5;
                }
            }
            foreach ($qKeywords as $kw) {
                if ($this->similarity->tokenMatchesConceptMap($kw, $conceptMap, $text)) {
                    $score += in_array($kw, $titleTerms, true) ? 1.5 : 2.5;
                }
            }
            foreach ($rawTerms as $term) {
                if ($this->containsWholeWordTerm($text, $term)) {
                    $score += 2.0;
                }
            }

            // Bonus frasa kata utuh berturut-turut (2+ kata) bila cocok tepat
            if (count($rawTerms) >= 2) {
                $phraseWords = array_slice($rawTerms, 0, 3);
                if (! empty($phraseWords) && $this->containsWholeWordPhrase($text, $phraseWords)) {
                    $score += 3.5;
                }
            }

            // Bonus bila kandidat berada dalam paragraf yang paling menjawab pertanyaan
            if ($bestParagraphText !== '' && $bestParaScore > 0 && str_contains(mb_strtolower($bestParagraphText), mb_strtolower(mb_substr($text, 0, 35)))) {
                $score += 2.5;
            }

            // Bonus kesesuaian jenis kalimat terhadap intent pertanyaan
            if ($intent === 'definition') {
                if (preg_match('/\b(?:adalah|merupakan|yaitu|didefinisikan|artinya|konsep\s+yang)\b/iu', $text) && ! preg_match('/^Contoh\b/iu', trim($text))) {
                    $score += 4.5;
                }
                if (preg_match('/\b(?:contoh\s+penggunaan|pencarian\s+dukungan)\b/iu', $text)) {
                    $score -= 1.0;
                }
            } elseif ($intent === 'function') {
                if (preg_match('/\b(?:fungsi\s+utama|fungsi|berfungsi|peran|berperan|tujuan|kedudukan|sarana|alat|penopang|menghemat|menjamin|melindungi)\b/iu', $text)) {
                    $score += 4.0;
                }
                if (preg_match('/\b(?:adalah\s+konsep|adalah\s+strategi|diperkenalkan\s+oleh)\b/iu', $text) && ! preg_match('/\bfungsi\b/iu', $text)) {
                    $score -= 2.5;
                }
            } elseif ($intent === 'reason') {
                if (preg_match('/\b(?:karena|sebab|sehingga|mengakibatkan|dampak|agar|supaya|guna|meminimalkan|diperlukan|keluar\s+adalah\s+sama)\b/iu', $text)) {
                    $score += 3.5;
                }
            } elseif ($intent === 'procedure') {
                if (preg_match('/\b(?:langkah|pertama|perintah|cara|membuat|aktifkan|salin|edit|strategi|prinsip|dilakukan|virtualhost|a2ensite|apache)\b/iu', $text)) {
                    $score += 3.5;
                }
            } elseif ($intent === 'example') {
                if (preg_match('/\b(?:contoh|seperti|mencakup|terdiri|aspek|yaitu|antara\s+lain|unsur|pasal|uud)\b/iu', $text)) {
                    $score += 3.5;
                }
            }

            // Bonus khusus bila pertanyaan meminta perintah teknis spesifik
            if (preg_match('/\b(?:perintah|command)\b/iu', $cleanQuestion)) {
                if (preg_match('/\b(?:perintah|a2ensite|service\s+apache2|force-reload|mkdir|nano|cp\s+|cd\s+)\b/iu', $text)) {
                    $score += 6.0;
                } else {
                    $score -= 2.5;
                }
            }
            if (str_contains($qLower, 'aktif') && preg_match('/\b(?:a2ensite|enabling\s+site|force-reload)\b/iu', $text)) {
                $score += 4.5;
            }

            // Penalti baris tabel PDF mentah yang menempel tanpa pemisah jelas & penalti shortcut editor
            if (preg_match('/[a-z][A-Z][a-z]+\s+utama\b|Pengguna\s+Peran\s+Kebutuhan/u', $text)) {
                $score -= 6.0;
            }
            if (! $asksAboutExit && preg_match('/\b(?:ctrl\s*\+\s*x|tekan\s+y|tekan\s+enter)\b/iu', $text)) {
                $score -= 5.0;
            }

            // Sedikit preferensi terhadap kalimat tunggal yang jernih daripada blok daftar tercampur jika skor hampir setara
            if ($cand['kind'] === 'sentence') {
                $score += 0.3;
            }

            $scored[] = ['cand' => $cand, 'score' => $score];
        }

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $topItems = [];
        $seenNormalized = [];
        $topScore = $scored[0]['score'] ?? 0.0;
        $allowMulti = ($intent === 'definition' && ($scored[0]['cand']['kind'] ?? 'sentence') === 'sentence')
            || (bool) preg_match('/\b(?:dasar\s+hukum|uud\s*1945)\b/iu', $cleanQuestion);

        foreach ($scored as $row) {
            if ($row['score'] <= 0 && ! empty($topItems)) {
                break;
            }
            if (! empty($topItems) && $row['score'] < max(1.0, $topScore * 0.72)) {
                break;
            }
            $txt = trim($row['cand']['text']);
            $norm = mb_strtolower($txt);
            // Hindari menambahkan kalimat yang sudah termuat di dalam blok/kalimat pertama
            $redundant = false;
            foreach ($seenNormalized as $existing) {
                if (str_contains($existing, $norm) || str_contains($norm, $existing)) {
                    $redundant = true;
                    break;
                }
            }
            if ($redundant) {
                continue;
            }
            $seenNormalized[] = $norm;
            $topItems[] = rtrim($txt, '.') . '.';
            if (! $allowMulti || count($topItems) >= 2 || mb_strlen(implode(' ', $topItems)) >= 280) {
                break;
            }
        }

        if (empty($topItems)) {
            $fallback = $sentences[0] ?? "Materi {$title} membahas konsep pokok serta penerapannya secara terstruktur.";
            $topItems[] = rtrim($fallback, '.') . '.';
        }

        $excerpt = implode(' ', $topItems);
        $recommendedAnswer = $this->paraphraseAnswerFromSource($excerpt, $intent, $title, $cleanQuestion);
        $difficulty = $this->classifyDifficultyHeuristically([
            'type' => 'essay',
            'question' => $cleanQuestion,
            'rubric' => $recommendedAnswer,
        ]);

        return [
            'recommended_answer' => $recommendedAnswer,
            'reference_excerpt' => $excerpt,
            'difficulty' => $difficulty,
            'difficulty_weight' => TeacherChallenge::weightForDifficulty($difficulty),
        ];
    }

    /**
     * Mengekstrak blok terstruktur (mis. daftar Kedudukan + Fungsi utama) dari materi
     * untuk melengkapi pencocokan semantik pertanyaan manual guru.
     *
     * @return array<int, string>
     */
    private function extractSectionBlocksForSearch(string $body, string $title): array
    {
        $lines = preg_split('/\R/u', $body) ?: [];
        $blocks = [];
        $currentGroup = [];
        $inCode = false;
        $skipSection = false;
        $pendingHeading = '';

        foreach ($lines as $rawLine) {
            $trimmed = trim($rawLine);
            if (str_starts_with($trimmed, '```')) {
                $inCode = ! $inCode;
                continue;
            }
            if ($inCode) {
                continue;
            }

            $plain = trim(preg_replace('/[*_#`]/u', '', strip_tags($rawLine)) ?? '');
            if ($plain === '') {
                continue;
            }
            if (preg_match('/^(?:TUJUAN PEMBELAJARAN|SUMBER RUJUKAN|DAFTAR PUSTAKA|TERIMA KASIH|REFERENSI)$/iu', $plain)) {
                $skipSection = true;
                $pendingHeading = '';
                continue;
            }
            if (preg_match('/^(?:DEFINISI|3 ASPEK|BAHASA INDONESIA|BAHASA DAERAH|BAHASA ASING|KESEIMBANGAN|KETERANGAN|BAB\s+)/iu', $plain)) {
                $skipSection = false;
                if (! empty($currentGroup)) {
                    $prefix = ! str_contains(mb_strtolower(implode(' ', $currentGroup)), mb_strtolower($plain))
                        ? "{$plain}: "
                        : '';
                    $blocks[] = $prefix . implode(' ', $currentGroup);
                    $currentGroup = [];
                    $pendingHeading = '';
                } else {
                    $pendingHeading = $plain;
                }
                continue;
            }
            if ($skipSection) {
                continue;
            }

            $itemPlain = trim(preg_replace('/^[\-\•\*]\s+/u', '', $plain) ?? $plain);

            if (preg_match('/^Kedudukan\s*:/iu', $itemPlain)) {
                if (! empty($currentGroup)) {
                    $blocks[] = ($pendingHeading !== '' ? "{$pendingHeading}: " : '') . implode(' ', $currentGroup);
                    $pendingHeading = '';
                }
                $currentGroup = [rtrim($itemPlain, ' .') . '.'];
            } elseif (! empty($currentGroup)) {
                if (preg_match('/^(?:Fungsi utama|Contoh penggunaan)\s*:/iu', $itemPlain)) {
                    $currentGroup[] = $itemPlain . (str_ends_with($itemPlain, ':') ? '' : '.');
                } elseif (preg_match('/^[\-\•]\s+/u', $trimmed)) {
                    if ($itemPlain !== '') {
                        $currentGroup[] = rtrim($itemPlain, '.') . ';';
                    }
                } else {
                    $blocks[] = ($pendingHeading !== '' ? "{$pendingHeading}: " : '') . implode(' ', $currentGroup);
                    $currentGroup = [];
                    $pendingHeading = '';
                }
            }
        }
        if (! empty($currentGroup)) {
            $blocks[] = ($pendingHeading !== '' ? "{$pendingHeading}: " : '') . implode(' ', $currentGroup);
        }

        return array_values(array_filter(array_map(function (string $b) use ($title): string {
            $clean = trim(preg_replace(['/\s+([,.;:])/u', '/;\./u', '/;$/u'], ['$1', '.', '.'], $b) ?? '');

            return mb_strlen($clean) >= 30 && mb_strlen($clean) <= 550 && mb_strtolower($clean) !== mb_strtolower($title) ? $clean : '';
        }, $blocks)));
    }

    /**
     * Memparafrasekan kalimat fakta dari materi PDF menjadi kunci jawaban yang alami,
     * objektif (bebas sapaan tutorial "anda/gambar di bawah"), serta langsung menjawab pertanyaan.
     */
    private function paraphraseAnswerFromSource(
        string $sourceSentence,
        string $role = 'general',
        string $title = '',
        string $question = '',
        string $subject = ''
    ): string {
        $text = trim(preg_replace('/\s+/u', ' ', $sourceSentence) ?? $sourceSentence);
        if ($text === '') {
            return '';
        }

        // 1. Gabungkan anak kalimat sebab-akibat yang terputus titik & bersihkan artefak slide/kutipan
        $text = preg_replace('/\.\s*Sehingga\s+(?:kita|kami)\s+lebih\s+menghemat\s+([^.]+)/iu', ', sehingga penggunaan $1 menjadi lebih hemat', $text) ?? $text;
        $text = preg_replace('/\.\s*(Sehingga|Akibatnya|Dengan demikian)\s+/iu', ', $1 ', $text) ?? $text;
        $text = preg_replace('/\.?\s*→\s*Tujuannya(?:\s+(?:untuk|adalah))?\s*/iu', ', dengan tujuan ', $text) ?? $text;
        $text = preg_replace('/\.?\s*(?:→|->)\s*/u', ', yaitu ', $text) ?? $text;
        $text = preg_replace('/\b(\p{L}{2,})\s+-\s+(\1)\b/iu', '$1-$2', $text) ?? $text;
        $text = preg_replace('/\bsehari\s*-\s*hari\b/iu', 'sehari-hari', $text) ?? $text;
        $text = preg_replace('/\s+&\s+/u', ' dan ', $text) ?? $text;
        $text = preg_replace('/^([A-Za-z][A-Za-z0-9\s-]{1,25})[”"]\s+artinya\s+(.+)$/iu', 'Prinsip "$1" berarti $2', $text) ?? $text;
        $text = preg_replace('/^(?:Tujuan\s*:\s*)/iu', 'Tujuan utamanya adalah ', $text) ?? $text;
        $text = preg_replace('/^Kedudukan\s*:\s*/iu', 'Kedudukan utamanya adalah sebagai ', $text) ?? $text;
        $text = preg_replace('/^Prinsip\s*:\s*/iu', 'Prinsip utama yang harus dijalankan adalah ', $text) ?? $text;
        $text = preg_replace('/^Fungsi(?:\s+utama)?\s*:\s*/iu', 'Fungsi utamanya meliputi ', $text) ?? $text;
        $text = preg_replace('/^(?:Landasan|Dasar)\s*:\s*/iu', 'Landasan pokoknya adalah ', $text) ?? $text;
        $text = preg_replace('/^(?:Oleh\s+karena\s+itu|Karena\s+itu|Oleh\s+sebab\s+itu|Dengan\s+demikian|Selain\s+itu|Di\s+samping\s+itu|Maka\s+dari\s+itu),?\s+/iu', '', $text) ?? $text;
        $text = preg_replace('/^(?:Akan tetapi|Namun),?\s+/iu', '', $text) ?? $text;

        // Bersihkan tanda kutip tidak berpasangan akibat pemotongan kalimat slide
        $openCurly = mb_substr_count($text, '“');
        $closeCurly = mb_substr_count($text, '”');
        if ($openCurly !== $closeCurly) {
            $text = str_replace(['“', '”'], '', $text);
        }
        if ((substr_count($text, '"') % 2) === 1) {
            $text = str_replace('"', '', $text);
        }

        // 2. Ubah ungkapan tutorial/informal, pronomina rancu, dan parafrasekan struktur kalimat secara alami
        $patterns = [
            '/\bIp\s+Address\b/iu' => 'IP Address',
            '/\bdirectori\b/iu' => 'direktori',
            '/\bmenganti\b/iu' => 'mengganti',
            '/\bmengarahkanya\b/iu' => 'mengarahkannya',
            '/\bdenga\s+/iu' => 'dengan ',
            '/\bmasukan\s+perintah\b/iu' => 'masukkan perintah',
            '/\bsemisal\s+/iu' => 'seperti ',
            '/^Perkembangan\s+ini\b/iu' => 'Perkembangan teknologi tersebut',
            '/^Penggunaannya\s+harus\s+disertai\b/iu' => 'Dalam praktiknya, pemanfaatannya perlu diimbangi dengan',
            '/\badalah\s+penyajian\s+kembali\s+suatu\s+teks\b/iu' => 'merupakan penyampaian ulang suatu bacaan',
            '/\bmerupakan\s+penyederhanaan\s+isi\s+teks\b/iu' => 'berfungsi menyederhanakan isi bacaan',
            '/\badalah\s+bentuk\s+penyajian\s+singkat\s+dari\s+teks\b/iu' => 'merupakan bentuk penulisan ringkas atas suatu bacaan',
            '/\btanpa\s+harus\s+mengikuti\s+struktur\b/iu' => 'tanpa terikat pada susunan',
            '/\btanpa\s+memperhatikan\s+urutan\s+asli\s+secara\s+ketat\b/iu' => 'tanpa harus mengikuti urutan aslinya secara kaku',
            '/\bdengan\s+cara\s+mengambil\b/iu' => 'dengan menyeleksi',
            '/\bmembawa\s+dampak\s+positif\s+seperti\b/iu' => 'memberikan manfaat nyata, antara lain berupa',
            '/\bharus\s+disertai\b/iu' => 'perlu diimbangi dengan',
            '/\bjangan\s+sampai\s+kita\s+tidak\s+menguasai\s+satu\s+([A-Za-z]+\s+asing)\s+pun\b/iu' => 'setiap individu perlu menguasai setidaknya satu $1',
            '/\bjangan\s+sampai\s+kita\s+tidak\s+menguasai\b/iu' => 'setiap individu dituntut untuk mampu menguasai',
            '/\bmemadukan\s+peran\b/iu' => 'menyeimbangkan peran',
            '/\bmenekankan\s+pada\s+tiga\s+aspek\s+utama\b/iu' => 'berfokus pada tiga pilar penting dalam',
            '/\bmenunjukkan\s+pemahaman\s+dan\s+interpretasi\s+pribadi\s+terhadap\s+teks\b/iu' => 'menegaskan pemahaman serta penafsiran pribadi penulis terhadap bacaan',
            '/\buntuk\s+memahami\s+keseluruhan\s+isi\s+bacaan\s+secara\s+cepat\b/iu' => 'agar pembaca dapat menangkap keseluruhan isi teks dengan lebih cepat',
            '/\buntuk\s+menyimpan\s+informasi\s+inti\b/iu' => 'guna merangkum poin-poin informasi inti',
            '/\bmasih\s+bisa\s+(?:kita|kami|anda)\s+tambahkan\s+lagi\b/iu' => 'dapat ditambahkan pula',
            '/\bjika\s+(?:anda|pengguna)\s+membuka\s+sub-?\s*domain\s+tersebut\s+maka\s+yang\s+keluar\s+adalah\s+sama,?\s*/iu' => 'secara bawaan seluruh sub-domain tersebut akan menampilkan isi halaman yang sama ketika dibuka apabila belum dipisahkan, ',
            '/\blalu\s+bagaimana\s+(?:anda|pengguna)\s+dapat\s+mengisi\s+atau\s+mengg?anti\s+dari\s+setiap\s+sub-?\s*domain\s+(?:anda|pengguna)\s+cukup\s+membuat\s+virtualhost\s+saja\s+lalu\s+(?:anda|pengguna)\s+aktifkan\s+melalui\s+apache\s+atau\s+(?:anda|pengguna)\s+dapat\s+mengarahkanny?a\s+ke\s+server\s+lain/iu' => 'sehingga agar isi setiap sub-domain dapat diisi atau dibedakan, administrator perlu membuat VirtualHost lalu mengaktifkannya melalui Apache atau mengarahkannya ke server lain',
            '/\bmemakai\s+AI\s+untuk\s+menguji\s+pemahaman,\s*bukan\s+untuk\s+merangkum\b/iu' => 'memanfaatkan kecerdasan buatan guna menguji pemahaman aktif siswa, alih-alih sekadar merangkum bacaan',
            '/\bSetiap\s+jawaban\s+wajib\s+disertai\b/iu' => 'di mana setiap respons peserta didik harus dilengkapi dengan',
            '/,?\s*\.\s*di\s+mana\b/iu' => ', di mana',
            '/\byaitu\s+saya\s+membuat\s+di\s+dalam\s+folder\s+([^\s]+)\s+saya\s+membuat\b/iu' => 'yakni di dalam folder $1 administrator membuat',
            '/\byaitu\s+saya\s+membuat\b/iu' => 'yakni administrator membuat',
            '/\bsaya\s+membuat\b/iu' => 'administrator membuat',
            '/\bkemudian\s+saya\s+isikan\b/iu' => 'lalu diisi dengan',
            '/\bdirancang\s+guna\s+meminimalkan\b/iu' => 'diterapkan untuk menekan dan mengurangi',
            '/\bmemerlukan\s+integrasi\s+modul\b/iu' => 'mewajibkan penerapan komponen',
            '/\bSimpan\s+dan\s+keluar\s+dengan\s+menekan\b/iu' => 'Setelah perubahan disimpan dan keluar dari editor dengan menekan tombol',
            '/\b,\s*kemudian\s+(?:anda|pengguna)\s+berpindah\s+(?:directory|direktori)\s+untuk\s+memulai\b/iu' => ', langkah berikutnya adalah beralih direktori guna memulai',
            '/\b(?:Anda|Pengguna)\s+berpindah\s+(?:directory|direktori)\s+ke\b/iu' => 'Administrator masuk ke direktori',
            '/\buntuk\s+mempermudah\s+konfigurasi\s+(?:silahkan|silakan)\s+(?:anda|pengguna)\s+salin\b/iu' => 'lalu menyalin',
            '/\bsetelah\s+(?:anda|pengguna)\s+salin\s+(?:silahkan|silakan)\s+(?:anda|pengguna)\s+edit\s+dengan\s+perintah\s*:?\s*/iu' => 'berkas hasil salinan tersebut kemudian disunting menggunakan perintah ',
            '/\bSetelah\s+itu\s+(?:anda|pengguna)\s+coba\s+buka\s+melalui\s+browser\s+client\b/iu' => 'Pengujian dilakukan dengan mengakses layanan melalui browser klien',
            '/\bjika\s+tidak\s+bisa\s+terbuka\s+atau\s+masih\s+sama\s+coba\s+(?:masukkan|masukan)\s+perintah\b/iu' => 'dan apabila halaman gagal diakses atau tampilannya belum berubah, jalankan perintah',
            '/\batau\s+tekan\s+f5\s+di\s*browser\s+(?:anda|pengguna)?\s*(?:atau\s+mungkin\s+lagi\s+periksa\s+konfigurasi\s+(?:anda|pengguna)?)?/iu' => ', muat ulang halaman browser (F5), serta periksa ulang konfigurasi yang diterapkan',
            '/\b(?:jika|apabila)\s+berhasil\s+akan\s+terbuka\s+di\s+(?:client|browser)(?:\s+seperti\s+(?:pada\s+)?gambar\s+di\s*bawah(?:\s+ini)?)?/iu' => 'agar layanan berhasil terbuka dengan semestinya pada sisi klien',
            '/,?\s*(?:dengan\s+perintah\s+|seperti\s+(?:pada\s+)?gambar\s+|seperti\s+)?di\s*bawah(?:\s+ini)?/iu' => '',
            '/\b(?:silahkan|silakan)\s+(?:anda|kamu)\s+/iu' => '',
            '/\b(?:anda|kamu)\s+(?:cukup|perlu|dapat|bisa)\s+/iu' => 'pengguna dapat ',
            '/\b(?:anda|kamu)\b/iu' => 'pengguna',
        ];

        foreach ($patterns as $regex => $replacement) {
            $text = preg_replace($regex, $replacement, $text) ?? $text;
        }

        $text = preg_replace('/\s+([,.;])/u', '$1', $text) ?? $text;
        $text = preg_replace('/,{2,}/u', ',', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text, " \t\n\r\0\x0B.");

        if ($text === '') {
            return rtrim($sourceSentence, '.') . '.';
        }

        $lowerFirst = mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);

        // 3. Sesuaikan pembuka jawaban agar langsung menjawab jenis pertanyaan yang diajukan secara berparafrase
        $qLower = mb_strtolower($question);
        if ($role === 'function_overview' && $subject !== '' && ! str_starts_with(mb_strtolower($text), 'fungsi')) {
            if (preg_match('/^Selain\s+domain\s+utama\s+seperti\s+([^,]+),\s*dapat\s+ditambahkan\s+pula\s+Sub-?Domain[^,]*,\s*sehingga\s+(.+)$/iu', $text, $sm)) {
                $text = "Fungsi dan gagasan utama penggunaan {$subject} adalah menambahkan domain turunan di bawah domain utama (seperti {$sm[1]}), sehingga {$sm[2]}";
            } else {
                $text = "Fungsi dan gagasan utama penggunaan {$subject} adalah bahwa {$lowerFirst}";
            }
        } elseif ($role === 'concept_summary' && $subject !== '' && ! preg_match('/^(?:Gagasan|Konsep|' . preg_quote($subject, '/') . ')/iu', $text)) {
            $text = "Gagasan utamanya adalah {$lowerFirst}";
        } elseif ($role === 'component' && $subject !== '' && str_contains($qLower, 'ciri khas') && preg_match('/^' . preg_quote($subject, '/') . '\s+(?:adalah|merupakan)\s+(.+)$/iu', $text, $cm)) {
            $text = "Ciri khas {$subject} terletak pada sifatnya sebagai " . mb_strtolower(mb_substr($cm[1], 0, 1)) . mb_substr($cm[1], 1);
        } elseif ($role === 'cause_effect' && str_contains($qLower, 'dampak positif') && ! str_starts_with(mb_strtolower($text), 'dampak positif')) {
            $text = "Dampak positifnya terlihat dari bagaimana {$lowerFirst}";
        } elseif ($role === 'application' && str_contains($qLower, 'kesadaran dan tanggung jawab moral') && ! str_contains(mb_strtolower($text), 'kesadaran dan tanggung jawab moral')) {
            $text = "Kesadaran dan tanggung jawab moral diperlukan karena {$lowerFirst}";
        } elseif ($role === 'reason' && str_contains($qLower, 'virtualhost') && ! str_starts_with(mb_strtolower($text), 'virtualhost diperlukan')) {
            $text = "VirtualHost diperlukan karena {$lowerFirst}";
        } elseif ($role === 'reason' && $subject !== '' && preg_match('/^Kedudukan\s+utamanya\s+adalah\s+sebagai\s+(.+)$/iu', $text, $km)) {
            $text = "{$subject} memiliki posisi strategis karena berperan sebagai {$km[1]} sesuai ketentuan yang berlaku";
        } elseif ($role === 'troubleshooting' && ! preg_match('/^(?:Apabila|Jika|Untuk|Pengujian|Langkah)/iu', $text)) {
            $text = 'Untuk menangani kendala tampilan atau akses, ' . $lowerFirst;
        } elseif ($role === 'procedure' && preg_match('/^Prinsip\s+utama\s+yang\s+harus\s+dijalankan\s+adalah\s+(.+)$/iu', $text, $pm)) {
            $bodyPrinsip = mb_strtolower(mb_substr($pm[1], 0, 1)) . mb_substr($pm[1], 1);
            $text = "Untuk menjaga keseimbangan peran kebahasaan, prinsip utama yang harus dijalankan adalah {$bodyPrinsip}";
        } elseif ($role === 'procedure' && preg_match('/^(?:Berkas|Salin|Gunakan|Edit)\b/iu', $text)) {
            $text = 'Pada tahap konfigurasi ini, ' . $lowerFirst;
        }

        // Rapikan huruf kapital setelah pembuka frase dengan huruf kecil (misal "Prinsip utama yang harus dijalankan adalah mengutamakan...")
        $text = preg_replace_callback(
            '/^(Prinsip utama yang harus dijalankan adalah|Kedudukan utamanya adalah sebagai|Fungsi utamanya meliputi|Landasan pokoknya adalah)\s+([A-Z])/u',
            fn (array $m) => $m[1] . ' ' . mb_strtolower($m[2]),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/\b(satu|suatu)\s+(Bahasa\s+(?:asing|daerah|nasional|negara))\b/u',
            fn (array $m) => $m[1] . ' ' . mb_strtolower($m[2]),
            $text
        ) ?? $text;

        $firstChar = mb_strtoupper(mb_substr($text, 0, 1));
        $text = $firstChar . mb_substr($text, 1);

        return rtrim($text, '.') . '.';
    }

    /**
     * Mengekstrak daftar pasangan Kunci = Nilai (misal ServerName = Nama domain) dari materi.
     *
     * @return array<string, string>
     */
    private function extractKeyValuePairs(string $body): array
    {
        $pairs = [];
        $lines = preg_split('/\R|\s+[\-•]\s+(?=[A-Z][A-Za-z0-9_]+\s*=)/u', $body) ?: [];
        foreach ($lines as $line) {
            $noMd = preg_replace('/[*`]/u', '', $line) ?? $line;
            $clean = trim(preg_replace('/^[\s\-\•]+/u', '', $noMd) ?? '');
            if (preg_match('/^([A-Z][A-Za-z0-9_]{2,30})\s*=\s*(.{8,140})$/u', $clean, $m)) {
                $val = rtrim(trim($m[2]), '.');
                if (str_word_count($val) >= 2) {
                    $pairs[trim($m[1])] = $val;
                }
            }
        }

        return $pairs;
    }

    /**
     * Menghasilkan Soal Pilihan Ganda berfokus substantif & analitis pada isi materi bacaan nyata
     * dengan jaminan 100% bebas soal duplikat/sama.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     */
    public function generateHeuristicQuizQuestions(string $body, string $title, ?string $category = null, int $count = 10): array
    {
        $count = min(100, max(5, $count));
        $sentences = $this->extractSubstantiveSentences($body, $title);

        if (empty($sentences)) {
            $sentences = [
                "Materi {$title} membahas prinsip dan mekanisme kerja utama secara terstruktur.",
                "Penerapan konsep pada {$title} diarahkan untuk meningkatkan keandalan dan efisiensi sistem.",
            ];
        }

        $termPool = $this->extractQuizKeyTerms($sentences);
        $predicatePool = $this->extractPredicatePool($sentences);
        $totalSentences = count($sentences);

        // Bangun daftar semua kandidat sudut pertanyaan unik untuk setiap kalimat terlebih dahulu
        $candidatesBySentence = [];
        foreach ($sentences as $idx => $sentence) {
            $candidatesBySentence[$idx] = $this->buildCandidateQuestionsFromSentence(
                $sentence,
                $sentences,
                $termPool,
                $predicatePool,
                $title
            );
        }

        $selectedItems = [];
        $usedQuestions = [];
        $bloomCycle = ['C1', 'C2', 'C3', 'C4', 'C5'];

        // Distribusi merata lintas kalimat sekaligus menyeimbangkan jenjang Bloom C1–C5 (termasuk Mengevaluasi)
        $maxRounds = max(array_map('count', $candidatesBySentence) ?: [1]) + 3;
        for ($round = 0; $round < $maxRounds && count($selectedItems) < $count; $round++) {
            $orderIndices = range(0, $totalSentences - 1);
            if ($totalSentences > $count && $round === 0) {
                $stepIndices = [];
                for ($k = 0; $k < $count; $k++) {
                    $stepIndices[] = (int) floor(($k * $totalSentences) / $count);
                }
                $orderIndices = array_values(array_unique(array_merge($stepIndices, $orderIndices)));
            }

            foreach ($orderIndices as $sIdx) {
                if (count($selectedItems) >= $count) {
                    break;
                }
                $targetBloom = $bloomCycle[count($selectedItems) % count($bloomCycle)];
                $chosenCand = null;

                // Prioritaskan kandidat yang cocok dengan target tingkat Bloom saat ini
                foreach ($candidatesBySentence[$sIdx] as $cand) {
                    $qKey = mb_strtolower(trim($cand['question']));
                    if (! isset($usedQuestions[$qKey]) && (($cand['bloom_level'] ?? '') === $targetBloom)) {
                        $chosenCand = $cand;
                        break;
                    }
                }
                if ($chosenCand === null) {
                    foreach ($candidatesBySentence[$sIdx] as $cand) {
                        $qKey = mb_strtolower(trim($cand['question']));
                        if (! isset($usedQuestions[$qKey])) {
                            $chosenCand = $cand;
                            break;
                        }
                    }
                }

                if ($chosenCand !== null) {
                    $usedQuestions[mb_strtolower(trim($chosenCand['question']))] = true;
                    $selectedItems[] = $chosenCand;
                }
            }
        }

        // Pengaman bila teks sangat mini namun target $count besar:
        // gunakan templat kata kerja Bloom C1–C5 tanpa menyalin paragraf utuh ke soal/opsi.
        $fallbackAttempt = 0;
        while (count($selectedItems) < $count && $fallbackAttempt < $count * 4) {
            $sIdx = $fallbackAttempt % $totalSentences;
            $sentence = $sentences[$sIdx];
            $conciseOption = $this->condenseClauseToOption($sentence);
            $otherPool = array_values(array_filter(
                array_map(fn ($s) => $this->condenseClauseToOption($s), $sentences),
                fn ($s) => mb_strtolower($s) !== mb_strtolower($conciseOption)
            ));
            $angleIndex = intdiv($fallbackAttempt, $totalSentences) % 5;
            $topicPhrase = $this->condenseSentenceToTopic($sentence, 6);
            $slotNum = count($selectedItems) + 1;
            $qPrompt = match ($angleIndex) {
                0 => "Identifikasi fakta pokok nomor {$slotNum} terkait \"{$topicPhrase}\" berdasarkan materi!",
                1 => "Jelaskan makna gagasan nomor {$slotNum} tentang \"{$topicPhrase}\" sesuai pembahasan!",
                2 => "Terapkan konsep \"{$topicPhrase}\" (butir {$slotNum}): tindakan manakah yang paling sesuai?",
                3 => "Analisis hubungan inti nomor {$slotNum} di balik konsep \"{$topicPhrase}\"!",
                default => "Evaluasi pernyataan nomor {$slotNum} manakah yang paling tepat mengenai \"{$topicPhrase}\"!",
            };
            $qKey = mb_strtolower($qPrompt);
            if (! isset($usedQuestions[$qKey])) {
                $usedQuestions[$qKey] = true;
                $selectedItems[] = [
                    'bloom_level' => $bloomCycle[$angleIndex],
                    'question' => $qPrompt,
                    'correct' => $conciseOption,
                    'distractors' => $otherPool,
                    'explanation' => "Pembahasan: Fakta pada materi menegaskan bahwa: \"{$sentence}\"",
                ];
            }
            $fallbackAttempt++;
        }

        $questions = [];
        foreach (array_slice($selectedItems, 0, $count) as $index => $built) {
            $seq = $index + 1;
            $options = $this->buildUniqueQuizOptions(
                $built['correct'],
                $built['distractors'],
                $seq,
                $termPool,
                $predicatePool,
                $built['question']
            );

            $explanationText = $built['explanation'];
            if (! str_contains($explanationText, 'Kunci Jawaban')) {
                $explanationText = "Kunci Jawaban ({$options['correct_key']}): {$built['correct']}. {$explanationText}";
            }

            $questions[] = [
                'sequence' => $seq,
                'question' => $built['question'],
                'options' => $options['options'],
                'correct_answer' => $options['correct_key'],
                'explanation' => $explanationText,
            ];
        }

        return $questions;
    }

    /**
     * Mengekstrak kalimat-kalimat isi materi yang bersih (menyaring tujuan pembelajaran, daftar pustaka,
     * dan baris perintah fragmen agar tidak menjadi soal berkualitas rendah).
     *
     * @return array<int, string>
     */
    private function extractSubstantiveSentences(string $body, string $title): array
    {
        $rawLines = preg_split('/\R/u', $body) ?: [];
        $lineBoilerplate = '/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you|halaman|page\s*\d+|lembar kerja|latar belakang|ringkasan eksekutif|rumusan masalah|proposal perancangan|menjelaskan pengertian|mengidentifikasi\s+(?:kedudukan|perbedaan)|menyusun\s+(?:strategi|ringkasan|rangkuman)|menunjukkan\s+(?:sikap|kemampuan))/iu';
        $biblioPattern = '/(?:^|\s)[\-\•]?\s*[A-Z][\p{L}\-]+,\s*[A-Z][\p{L}\.\s,dkk]*\(\d{4}\)/u';
        $publisherPattern = '/\b(?:Raja\s+Grafindo\s+Persada|Balai\s+Pustaka|Nusa\s+Indah|Angkasa|Gramedia|Erlangga|Kemdikbud|Rineka\s+Cipta|Pusat\s+Pembinaan|Remaja\s+Rosdakarya|Kencana|Alfabeta|Pustaka\s+Pelajar|Bumi\s+Aksara)\b/iu';
        $titleLower = mb_strtolower(trim($title));

        $keptLines = [];
        $inCode = false;
        $inBibliographySection = false;
        $inObjectiveSection = false;

        foreach ($rawLines as $rawLine) {
            $trimmedRaw = trim($rawLine);
            if (str_starts_with($trimmedRaw, '```')) {
                $inCode = ! $inCode;
                continue;
            }
            if ($inCode) {
                continue;
            }

            $line = trim(preg_replace('/[*_#`]/u', '', strip_tags($rawLine)) ?? '');
            $line = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $line) ?? $line;
            $line = preg_replace('/^([A-Z\s]{6,45})\1$/u', '$1', trim($line)) ?? trim($line);
            $line = preg_replace('/\b([\p{L}\-]{3,25})(?:\s+\1){1,3}\b/iu', '$1', $line) ?? $line;
            $line = preg_replace('/\b([\p{L}\-,&]{4,60}(?:\s+[\p{L}\-,&]{2,30}){1,6})\s+\1\b/iu', '$1', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                $keptLines[] = '';
                continue;
            }

            if (str_ends_with($line, ',') && mb_strlen($line) < 95 && ! preg_match('/\b(?:adalah|merupakan|yaitu|bahwa)\b/iu', $line)) {
                continue;
            }
            if (preg_match('/^Aspek\b/u', $line) && ! preg_match('/\b(?:adalah|merupakan|yaitu|karena|sehingga)\b/iu', $line) && substr_count($line, '.') === 0) {
                continue;
            }

            if (preg_match('/^(?:SUMBER RUJUKAN|DAFTAR PUSTAKA|REFERENSI)\b/iu', $line)) {
                $inBibliographySection = true;
                $inObjectiveSection = false;
                continue;
            }
            if (preg_match('/^(?:TUJUAN PEMBELAJARAN|CAPAIAN PEMBELAJARAN)\b/iu', $line)) {
                $inObjectiveSection = true;
                continue;
            }
            if (preg_match('/^Setelah mengikuti pembelajaran/iu', $line)) {
                $inObjectiveSection = true;
                continue;
            }
            if (preg_match('/^(?:DEFINISI|3 ASPEK|BAHASA INDONESIA|BAHASA DAERAH|BAHASA ASING|KESEIMBANGAN|BAB\s+)/iu', $line) || preg_match('/\b(?:adalah|merupakan)\b/iu', $line)) {
                $inObjectiveSection = false;
                $inBibliographySection = false;
            }

            if (preg_match($biblioPattern, $line)) {
                $line = trim((string) preg_replace('/\s*[\-\•]?\s*(?:[A-Z][\p{L}\-]+,\s*[A-Z][\p{L}\.\s,dkk&]*)*[A-Z][\p{L}\-]+,\s*[A-Z][\p{L}\.\s,dkk]*\(\d{4}\).*$/us', '', $line));
                if ($line === '' || preg_match('/(?:^[A-Z][\p{L}\-]+,\s*[A-Z]\.|&\s*$)/u', $line)) {
                    $inBibliographySection = true;
                    continue;
                }
            }
            if (preg_match('/\bReferensi\s*$/iu', $line) && preg_match($publisherPattern, $line)) {
                continue;
            }

            if ($inBibliographySection || $inObjectiveSection) {
                if (preg_match($biblioPattern, $line) || preg_match($publisherPattern, $line) || preg_match($lineBoilerplate, preg_replace('/^[\-\•\d\.\s]+/u', '', $line))) {
                    continue;
                }
            }

            if (mb_strtolower($line) === $titleLower) {
                continue;
            }
            if (preg_match('/\p{L}/u', $line) && mb_strtoupper($line) === $line && mb_strlen($line) < 120) {
                continue;
            }
            if (preg_match($lineBoilerplate, preg_replace('/^[\-\•\d\.\s]+/u', '', $line)) && mb_strlen($line) < 120) {
                continue;
            }
            $lineNoBullet = trim(preg_replace('/^[\-\•\d\.\s]+/u', '', $line) ?? $line);
            if (preg_match($biblioPattern, $line) || preg_match($biblioPattern, $lineNoBullet) || preg_match($publisherPattern, $lineNoBullet)) {
                continue;
            }
            if (preg_match('/^[A-Z][\p{L}\-]+:\s*(?:Balai Pustaka|Kemdikbud|Rineka Cipta|Pusat Pembinaan|Remaja Rosdakarya)/iu', $lineNoBullet)) {
                continue;
            }
            if (str_ends_with($line, ':') && mb_strlen($line) < 70) {
                continue;
            }
            if ((str_contains($line, '·') || str_contains($line, '@')) && mb_strlen($line) < 90) {
                continue;
            }
            if (preg_match('/(?:logicodix|subtema:|@[a-z0-9_]+|\b\d{4}\s*·|masukan\s+perintah\s*:\s*$|masukkan\s+perintah\s*:\s*$)/iu', $line)) {
                continue;
            }
            $line = preg_replace('/\s*Proposal Perancangan Aplikasi:\s*SocratesRead AI\s*/iu', ' ', $line) ?? $line;
            $line = preg_replace('/\bDDo\s+S\b/u', 'DDoS', $line) ?? $line;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $keptLines[] = $line;
        }

        // Gabungkan soft-wrap baris PDF tanpa menggabungkan item list baru
        $mergedParagraphs = [];
        $current = '';
        $startsWithListMarker = '/^(?:[•·\-\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2192}\x{2713}\x{2714}⚬○■□▪▫◆◇]+|\d+[\.\)])\s+/u';

        foreach ($keptLines as $line) {
            if ($line === '') {
                if ($current !== '') {
                    $mergedParagraphs[] = $current;
                    $current = '';
                }
                continue;
            }

            if ($current === '') {
                $current = $line;
            } elseif (preg_match($startsWithListMarker, $line) || preg_match('/[.?!]$/u', $current)) {
                $mergedParagraphs[] = $current;
                $current = $line;
            } else {
                $current .= ' ' . $line;
            }
        }
        if ($current !== '') {
            $mergedParagraphs[] = $current;
        }

        $bulletPattern = '/^(?:[•·\-\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2192}\x{2713}\x{2714}⚬○■□▪▫◆◇]+|\d+[\.\)])\s*/u';
        $cleanSentences = [];
        $seen = [];

        foreach ($mergedParagraphs as $para) {
            $para = preg_replace('/[ \t]+/u', ' ', $para) ?? $para;
            // Pecah butir daftar sebaris ("... Bahasa. - Trigatra...") atau kalimat panjang
            $bulletSplit = preg_split('/\s+[\-•]\s+(?=[A-Z0-9"“‘])/u', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [$para];
            $split = [];
            foreach ($bulletSplit as $chunk) {
                $sentParts = preg_split('/(?<=[.?!])["”]?\s+(?=[A-Z0-9"“])/u', $chunk, -1, PREG_SPLIT_NO_EMPTY) ?: [$chunk];
                foreach ($sentParts as $sp) {
                    $split[] = $sp;
                }
            }

            // Gabungkan subjudul pendek berhuruf (< 34 char, mis. "Mekanisme anti-mencontek.") ke kalimat penjelas berikutnya
            $stitchedSplit = [];
            $leadBuffer = '';
            foreach ($split as $segment) {
                $segClean = trim($segment);
                if ($leadBuffer !== '') {
                    $segClean = rtrim($leadBuffer, '.') . ': ' . trim(preg_replace($bulletPattern, '', $segClean) ?? $segClean);
                    $leadBuffer = '';
                }
                $segNoBullet = trim(preg_replace($bulletPattern, '', $segClean) ?? $segClean);
                if ($segNoBullet !== '' && mb_strlen($segNoBullet) < 34 && str_ends_with($segNoBullet, '.') && ! str_contains($segNoBullet, '(') && str_word_count($segNoBullet) >= 2) {
                    $leadBuffer = $segNoBullet;
                    continue;
                }
                $stitchedSplit[] = $segClean;
            }
            if ($leadBuffer !== '') {
                $stitchedSplit[] = $leadBuffer;
            }

            // Pecah kalimat run-on PDF panjang (> 330 karakter) pada penanda transisi klausa alami
            $refinedSplit = [];
            foreach ($stitchedSplit as $segment) {
                if (mb_strlen($segment) > 330) {
                    $subParts = preg_split('/(?<=.{45})\s+(?=(?:akan tetapi|namun demikian|oleh karena itu|selain itu|langkah berikutnya|setelah itu)\b)/iu', $segment, -1, PREG_SPLIT_NO_EMPTY) ?: [$segment];
                    foreach ($subParts as $sub) {
                        $refinedSplit[] = $sub;
                    }
                } else {
                    $refinedSplit[] = $segment;
                }
            }

            foreach ($refinedSplit as $part) {
                $s = trim(preg_replace($bulletPattern, '', trim($part)) ?? '');
                if (! preg_match('/\b(?:Pasal|Bab|Ayat|UUD|Tahun|Nomor|No|Ke)\s+\d+\.?$/iu', $s)) {
                    $s = trim(preg_replace('/\s+\d+\.?$/u', '', $s) ?? '');
                }
                $s = preg_replace('/\b([\p{L}\-]{3,25})(?:\s+\1){1,3}\b/iu', '$1', $s) ?? $s;
                $s = trim($s, "\"“”' ");

                if ($s === '' || mb_strlen($s) < 28 || mb_strlen($s) > 540) {
                    continue;
                }
                if (preg_match($lineBoilerplate, $s) || preg_match($biblioPattern, $s) || preg_match($publisherPattern, $s)) {
                    continue;
                }
                if (preg_match('/^[A-Z][\p{L}\-]+:\s*(?:Balai Pustaka|Kemdikbud|Rineka Cipta|Pusat Pembinaan|Remaja Rosdakarya)/iu', $s)) {
                    continue;
                }
                if (preg_match('/\p{L}/u', $s) && mb_strtoupper($s) === $s) {
                    continue;
                }
                if (str_ends_with($s, '?') || str_ends_with($s, ':') || preg_match('/\b(?:masukan|masukkan)\s+perintah\s*:\s*$/iu', $s)) {
                    continue;
                }
                $words = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($words) < 5) {
                    continue;
                }
                if (preg_match('/^Ciri\s*:/iu', $s) && count($words) < 12) {
                    continue;
                }
                if (preg_match('/\b\d+\.(?:\s*)?(?:Baca|Tandai|Buat|Tulis|Periksa|Catat|Gabungkan|Susun|Gunakan|Tentukan|Pahami|Sertakan|Pilih)\b/u', $s) && ! preg_match('/\b(?:adalah|merupakan|yaitu|karena|sehingga|membawa)\b/iu', $s)) {
                    continue;
                }
                if (count($words) < 10 && preg_match('/^(?:Baca|Tandai|Buat|Tulis|Periksa|Catat|Gabungkan|Susun|Gunakan|Tentukan|Pahami|Sertakan|Pilih|Saya\s+setuju)\b/iu', $s) && ! preg_match('/\b(?:adalah|merupakan|yaitu|karena|sehingga|agar|untuk)\b/iu', $s)) {
                    continue;
                }
                if (preg_match('/^Saya\s+setuju\b/iu', $s)) {
                    continue;
                }

                $key = mb_strtolower($s);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $cleanSentences[] = $s;
                }
            }
        }

        return $cleanSentences;
    }

    /**
     * @param array<int, string> $sentences
     * @return array<int, string>
     */
    private function extractQuizKeyTerms(array $sentences): array
    {
        $terms = [];
        $seen = [];

        $addTerm = function (string $term) use (&$terms, &$seen): void {
            $clean = trim($term, " \t\n\r\0\x0B.,;:!?\"'()");
            $clean = preg_replace('/\s+(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|ini|itu|tersebut)$/iu', '', $clean) ?? $clean;
            $clean = preg_replace('/^(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|ini|itu|tersebut)\s+/iu', '', $clean) ?? $clean;
            $clean = trim($clean);
            if (mb_strlen($clean) < 4 || mb_strlen($clean) > 55) {
                return;
            }
            if (preg_match('/^(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|adalah|merupakan|yaitu|ini|itu|tersebut|sehingga|karena|bahwa|akan|telah|dapat|harus|lebih|sangat|secara|antara|melalui|setelah)$/iu', $clean)) {
                return;
            }
            if (preg_match('/^([\p{L}]+)-\1$/iu', $clean) || mb_strtolower($clean) === 'benar-benar' || str_contains($clean, '=')) {
                return;
            }
            if (preg_match('/^\d+(?:[.,]\d+)?\s*(?:-|%|bit|persen|terabita|gb|mb|kb)/iu', $clean)) {
                return;
            }
            $lower = mb_strtolower($clean);
            if (! isset($seen[$lower])) {
                $seen[$lower] = true;
                $terms[] = $clean;
            }
        };

        foreach ($sentences as $s) {
            if (preg_match_all('/\(([^()]{3,40})\)/u', $s, $m)) {
                foreach ($m[1] as $t) {
                    $addTerm($t);
                }
            }
            if (preg_match_all('/\b[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)+\b/u', $s, $m)) {
                foreach ($m[0] as $t) {
                    if (! preg_match('/-nya$/iu', $t)) {
                        $addTerm($t);
                    }
                }
            }
            if (preg_match_all('/\b[A-Z][\p{L}\p{N}-]*(?:\s+[A-Z][\p{L}\p{N}-]*){1,3}\b/u', $s, $m)) {
                foreach ($m[0] as $t) {
                    $addTerm($t);
                }
            }
            if (preg_match_all('/\b(?:modul|algoritma|metodologi|arsitektur|protokol|sistem|kapasitas|infrastruktur|serangan|efisiensi|latensi|utilisasi|enkripsi|komputasi|database|basis data|jaringan|performa|indeks|tabel|bahasa|identitas|kebudayaan|diplomasi|strategi|prinsip)\s+[\p{L}\p{N}-]+(?:\s+[\p{L}\p{N}-]+)?/iu', $s, $m)) {
                foreach ($m[0] as $t) {
                    $addTerm($t);
                }
            }
        }

        return $terms;
    }

    /**
     * @param array<int, string> $sentences
     * @return array<int, string>
     */
    private function extractPredicatePool(array $sentences): array
    {
        $pool = [];
        foreach ($sentences as $s) {
            if (preg_match('/^.{8,70}?\s+(?:adalah|merupakan|yaitu|yakni|dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|berfungsi\s+sebagai|berperan\s+sebagai|mendeteksi|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan|memiliki\s+makna|artinya)\s+(.+)$/iu', $s, $m)) {
                $clause = ucfirst(rtrim(trim($m[1]), '.'));
                if (mb_strlen($clause) >= 15) {
                    $pool[] = $clause;
                }
            } else {
                $pool[] = rtrim($s, '.');
            }
        }

        return array_values(array_unique($pool));
    }

    /**
     * Membangun seluruh kandidat soal pilihan ganda berjenjang Taksonomi Bloom (C1–C5)
     * tanpa menyalin paragraf utuh ke soal maupun opsi jawaban.
     *
     * @param array<int, string> $allSentences
     * @param array<int, string> $termPool
     * @param array<int, string> $predicatePool
     * @return array<int, array{bloom_level: string, question: string, correct: string, distractors: array<int, string>, explanation: string}>
     */
    private function buildCandidateQuestionsFromSentence(
        string $sentence,
        array $allSentences,
        array $termPool,
        array $predicatePool,
        string $title = ''
    ): array {
        $candidates = [];
        $condensedOptionPool = array_values(array_filter(
            array_map(fn ($p) => $this->condenseClauseToOption($p), $predicatePool),
            fn ($p) => $p !== ''
        ));

        // Angle 1 (C1 Mengingat): Fakta Pasal Hukum / Besaran Teknis (tanpa menyalin kalimat utuh)
        if (preg_match('/\b(Pasal\s+\d+(?:\s+ayat\s*\(\d+\))?)\b/iu', $sentence, $pasalMatch)) {
            $pasal = trim($pasalMatch[1]);
            $withoutPasal = trim(preg_replace('/\b(?:UUD\s*1945\s*)?' . preg_quote($pasal, '/') . '\b/iu', '', $sentence) ?? $sentence);
            $topicSummary = $this->condenseSentenceToTopic($withoutPasal, 8);
            $candidates[] = [
                'bloom_level' => 'C1',
                'question' => "Identifikasi pasal UUD yang menjadi dasar hukum terkait \"{$topicSummary}\"!",
                'correct' => $pasal,
                'distractors' => ['Pasal 27', 'Pasal 28E', 'Pasal 31', 'Pasal 33', 'Pasal 34', 'Pasal 35'],
                'explanation' => "Pembahasan: Ketentuan tersebut diatur secara eksplisit dalam {$pasal} sesuai pernyataan: \"{$sentence}\"",
            ];
        }

        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*(?:-\s*bit|%|persen|Terabita|Gigabita|Megabita|TB|GB|MB|KB|transaksi(?:\s+per\s+detik)?|detik|menit|jam|hari|tahun)\b/iu', $sentence, $numMatches)) {
            foreach ($numMatches[0] as $rawToken) {
                $token = trim($rawToken);
                $withoutToken = trim(preg_replace('/' . preg_quote($token, '/') . '/u', '', $sentence, 1) ?? $sentence);
                $topicSummary = $this->condenseSentenceToTopic($withoutToken, 8);
                if ($topicSummary !== '') {
                    $candidates[] = [
                        'bloom_level' => 'C1',
                        'question' => "Sebutkan nilai atau besaran tepat yang dijelaskan terkait \"{$topicSummary}\"!",
                        'correct' => $token,
                        'distractors' => $this->generateNumericDistractors($token),
                        'explanation' => "Pembahasan: Besaran yang tepat adalah {$token}, sebagaimana dijelaskan pada fakta materi: \"{$sentence}\"",
                    ];
                }
            }
        }

        // Angle 2 (C2 Memahami / C4 Menganalisis): Makna, Peran, & Cakupan Unsur
        if (preg_match('/^(.{4,65}?)\s+(adalah|merupakan|yaitu|yakni|didefinisikan sebagai|berfungsi sebagai|berperan sebagai|mencakup|terdiri dari|memiliki makna|artinya)\s+(.+)$/iu', $sentence, $m)) {
            $subject = trim($m[1], " \t\"'“”‘’");
            $verb = mb_strtolower(trim($m[2]));
            $definition = $this->condenseClauseToOption(ucfirst(rtrim(trim($m[3]), '.')));

            [$bloom, $qPrompt] = match (true) {
                str_contains($verb, 'berfungsi'), str_contains($verb, 'berperan') => ['C2', "Jelaskan fungsi atau peran utama dari konsep \"{$subject}\"!"],
                str_contains($verb, 'mencakup'), str_contains($verb, 'terdiri') => ['C4', "Analisis unsur atau cakupan penting yang membangun \"{$subject}\"!"],
                str_contains($verb, 'makna'), str_contains($verb, 'artinya') => ['C2', "Jelaskan makna konsep \"{$subject}\" sesuai inti pembahasan!"],
                default => ['C2', "Jelaskan pengertian paling tepat mengenai \"{$subject}\"!"],
            };

            $candidates[] = [
                'bloom_level' => $bloom,
                'question' => $qPrompt,
                'correct' => $definition,
                'distractors' => $condensedOptionPool,
                'explanation' => "Pembahasan: Konsep \"{$subject}\" dijelaskan sebagai {$definition}. Hal ini menjadi inti pemahaman pada bagian tersebut.",
            ];
        }

        // Angle 3 (C3 Menerapkan): Tujuan & Penerapan Langkah
        if (preg_match('/^(.{15,150}?)\s+(?:dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|untuk|guna|demi|agar)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectAction = $this->condenseSentenceToTopic(rtrim(trim($m[1]), ', '), 5);
            $goal = $this->condenseClauseToOption(ucfirst(rtrim(trim($m[2]), '.')));
            $candidates[] = [
                'bloom_level' => 'C3',
                'question' => "Terapkan pemahaman materi: untuk sasaran apa \"{$subjectAction}\" dijalankan?",
                'correct' => $goal,
                'distractors' => array_map(fn ($g) => $this->condenseClauseToOption($g), $this->buildGoalDistractors($goal, $allSentences, $predicatePool)),
                'explanation' => "Pembahasan: Tindakan \"{$subjectAction}\" ditujukan untuk " . lcfirst($goal) . '.',
            ];
        }

        // Angle 3b (C4 Menganalisis): Temuan / Mekanisme / Hubungan Kausal
        if (preg_match('/^(.{15,140}?)\s+(mendeteksi\s+(?:adanya\s+)?|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan|disebabkan\s+oleh|karena)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectPart = $this->condenseSentenceToTopic(rtrim(trim($m[1]), ', '), 5);
            $connector = mb_strtolower(trim($m[2]));
            $detail = $this->condenseClauseToOption(ucfirst(rtrim(trim($m[3]), '.')));

            $qPrompt = match (true) {
                str_contains($connector, 'mendeteksi') => "Analisis temuan apa yang berhasil diidentifikasi oleh \"{$subjectPart}\"!",
                str_contains($connector, 'menunjukkan') => "Analisis simpulan utama yang ditunjukkan oleh \"{$subjectPart}\"!",
                str_contains($connector, 'bertumpu') => "Analisis prinsip utama yang menjadi tumpuan \"{$subjectPart}\"!",
                str_contains($connector, 'memerlukan') => "Analisis kebutuhan pokok yang diperlukan oleh \"{$subjectPart}\"!",
                default => "Analisis alasan mengapa kondisi \"{$subjectPart}\" terjadi dalam materi!",
            };

            $candidates[] = [
                'bloom_level' => 'C4',
                'question' => $qPrompt,
                'correct' => $detail,
                'distractors' => $condensedOptionPool,
                'explanation' => "Pembahasan: Hubungan kausal/mekanisme dalam materi menjelaskan bahwa: \"{$sentence}\"",
            ];
        }

        // Angle 4 (C5 Mengevaluasi & C3 Menerapkan): Evaluasi Pernyataan Berdasarkan Istilah Kunci (pencarian kata utuh)
        $matchedTerms = [];
        foreach ($termPool as $term) {
            if ($this->containsWholeWordTerm($sentence, $term) && preg_match('/(?<!\p{L}\p{N})' . preg_quote($term, '/') . '(?!\p{L}\p{N})/iu', $sentence, $tm)) {
                $matchedTerms[] = $tm[0];
            }
        }
        $condensedCoreOption = $this->condenseClauseToOption($sentence);
        $otherOptionPool = array_values(array_filter(
            array_map(fn ($s) => $this->condenseClauseToOption($s), $allSentences),
            fn ($s) => $s !== '' && mb_strtolower($s) !== mb_strtolower($condensedCoreOption)
        ));

        foreach (array_slice($matchedTerms, 0, 2) as $idxTerm => $concept) {
            $conceptDistractors = array_values(array_filter(
                $otherOptionPool,
                fn ($s) => ! $this->containsWholeWordTerm($s, $concept)
            ));
            [$bloom, $promptByConcept] = $idxTerm === 0
                ? ['C5', "Evaluasi pernyataan manakah yang paling tepat dan sesuai prinsip materi mengenai \"{$concept}\"!"]
                : ['C3', "Tentukan penerapan atau ketentuan yang paling sesuai dengan konsep \"{$concept}\" pada materi \"{$title}\"!"];

            $candidates[] = [
                'bloom_level' => $bloom,
                'question' => $promptByConcept,
                'correct' => $condensedCoreOption,
                'distractors' => ! empty($conceptDistractors) ? $conceptDistractors : $otherOptionPool,
                'explanation' => "Pembahasan: Evaluasi materi mengenai konsep \"{$concept}\" menegaskan bahwa: {$condensedCoreOption}. Opsi lain tidak mencerminkan prinsip utama bacaan.",
            ];
        }

        // Angle 5 (C4 Menganalisis & C5 Mengevaluasi): Peranan Struktural & Evaluasi Prinsip (tanpa pemotongan acak di tengah kalimat)
        if (preg_match('/^(.{4,55}?)\s+(?:berfungsi|berperan|bertujuan|memadukan|menuntut|mengatur|menjamin|melindungi|mencakup|menjadi)\s+(.{12,})$/iu', $sentence, $predMatch)) {
            $entitySubject = trim($predMatch[1], " \t\"'“”‘’");
            $predicateClause = $this->condenseClauseToOption(ucfirst(rtrim(trim($predMatch[2]), '.')));
            $qAnalisis = "Analisis fungsi atau peranan penting dari \"{$entitySubject}\" menurut materi!";
            if (mb_strlen($predicateClause) >= 12 && ! $this->isQuestionLeakingAnswer($qAnalisis, $predicateClause)) {
                $candidates[] = [
                    'bloom_level' => 'C4',
                    'question' => $qAnalisis,
                    'correct' => $predicateClause,
                    'distractors' => $condensedOptionPool,
                    'explanation' => "Pembahasan: Berdasarkan analisis materi, \"{$entitySubject}\" memiliki peran penting dalam " . lcfirst($predicateClause) . ". Pemahaman ini menjadi kunci dalam menguraikan hubungan fungsional materi.",
                ];
            }
        }

        $cleanTopic = $this->condenseSentenceToTopic($sentence, 5);
        if ($cleanTopic !== '' && mb_strlen($condensedCoreOption) >= 15) {
            $qEvaluasi = "Evaluasi kesimpulan manakah yang paling tepat menilai gagasan tentang \"{$cleanTopic}\"?";
            if (! $this->isQuestionLeakingAnswer($qEvaluasi, $condensedCoreOption)) {
                $candidates[] = [
                    'bloom_level' => 'C5',
                    'question' => $qEvaluasi,
                    'correct' => $condensedCoreOption,
                    'distractors' => $otherOptionPool,
                    'explanation' => "Pembahasan: Evaluasi yang paling tepat mengenai gagasan \"{$cleanTopic}\" berdasar pada prinsip bacaan: \"{$sentence}\". Opsi lain tidak selaras dengan prinsip dasar tersebut.",
                ];
            }
        }

        return array_values(array_filter($candidates, function ($c) {
            if ($this->isQuestionLeakingAnswer($c['question'], $c['correct'])) {
                return false;
            }
            if (preg_match('/^[&+\-*\/,;:|~_–—]|^(?:dan|atau|serta|yaitu|yakni|adalah|merupakan|bahwa)\b/iu', trim($c['correct']))) {
                return false;
            }
            return true;
        }));
    }

    /**
     * @return array<int, string>
     */
    private function generateNumericDistractors(string $token): array
    {
        if (! preg_match('/^(\d+(?:[.,]\d+)?)(.*)$/u', trim($token), $m)) {
            return [];
        }

        $rawNum = $m[1];
        $suffix = $m[2];
        $usesComma = str_contains($rawNum, ',');
        $usesDotThousand = (bool) preg_match('/^\d{1,3}(?:\.\d{3})+$/', $rawNum);

        $num = $usesDotThousand
            ? (float) str_replace('.', '', $rawNum)
            : (float) str_replace(',', '.', $rawNum);

        if (str_contains(mb_strtolower($suffix), 'bit') && in_array((int) $num, [128, 256, 512, 1024, 2048, 4096], true)) {
            $pool = [256, 512, 1024, 2048, 4096];
            $out = [];
            foreach ($pool as $v) {
                if ($v !== (int) $num) {
                    $out[] = $v . $suffix;
                }
            }

            return array_slice($out, 0, 3);
        }

        $multipliers = [0.5, 1.5, 2.0, 0.25, 2.5];
        $distractors = [];
        foreach ($multipliers as $mult) {
            $val = $num * $mult;
            if (abs($val - $num) < 0.01) {
                continue;
            }
            if ($usesDotThousand) {
                $formatted = number_format((int) round($val), 0, ',', '.');
            } elseif ($usesComma) {
                $formatted = str_replace('.', ',', number_format($val, 1, '.', ''));
            } else {
                $formatted = (string) max(1, (int) round($val));
            }
            $candidate = $formatted . $suffix;
            if ($candidate !== $token && ! in_array($candidate, $distractors, true)) {
                $distractors[] = $candidate;
            }
            if (count($distractors) >= 3) {
                break;
            }
        }

        return $distractors;
    }

    /**
     * @param array<int, string> $allSentences
     * @param array<int, string> $predicatePool
     * @return array<int, string>
     */
    private function buildGoalDistractors(string $correctGoal, array $allSentences, array $predicatePool): array
    {
        $goals = [];
        foreach ($allSentences as $s) {
            if (preg_match('/(?:dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|untuk|guna|demi|agar)\s+(.{15,})$/iu', $s, $m)) {
                $g = ucfirst(rtrim(trim($m[1]), '.'));
                if (mb_strtolower($g) !== mb_strtolower($correctGoal)) {
                    $goals[] = $g;
                }
            }
        }

        return array_values(array_unique(array_merge($goals, $predicatePool)));
    }

    /**
     * @param array<int, string> $candidateDistractors
     * @param array<int, string> $termPool
     * @param array<int, string> $predicatePool
     * @return array{options: array<int, array{key: string, text: string}>, correct_key: string}
     */
    private function buildUniqueQuizOptions(
        string $correct,
        array $candidateDistractors,
        int $sequence,
        array $termPool,
        array $predicatePool,
        string $question = ''
    ): array {
        $correctClean = trim($correct);
        $usedLower = [mb_strtolower($correctClean) => true];
        $distractors = [];

        $isPasalCorrect = (bool) preg_match('/^Pasal\s+\d+/iu', $correctClean);
        $isNumericCorrect = (bool) preg_match('/^\d+(?:[.,]\d+)?\s*[A-Za-z%]+/iu', $correctClean);

        if ($isPasalCorrect) {
            $mergedPool = array_merge($candidateDistractors, ['Pasal 27', 'Pasal 28E', 'Pasal 29', 'Pasal 31', 'Pasal 32', 'Pasal 33', 'Pasal 34', 'Pasal 35', 'Pasal 36']);
        } elseif ($isNumericCorrect) {
            $mergedPool = $candidateDistractors;
        } else {
            $neutralFallbacks = [
                'Mengabaikan kaidah inti yang berlaku pada pembahasan materi',
                'Menjalankan langkah yang bertentangan dengan prinsip utama bacaan',
                'Menyamakan seluruh konsep tanpa memperhatikan batasan fungsi',
                'Menunda penerapan prosedur utama tanpa dasar yang jelas',
            ];
            $condensedPredicates = array_values(array_filter(
                array_map(fn ($p) => $this->condenseClauseToOption((string) $p), $predicatePool),
                fn ($p) => $p !== ''
            ));
            $mergedPool = array_merge($candidateDistractors, $condensedPredicates, $termPool, $neutralFallbacks);
        }

        foreach ($mergedPool as $cand) {
            $c = trim((string) $cand);
            if (! $this->isPlausibleQuizOption($c, $correctClean, $question)) {
                continue;
            }
            $lower = mb_strtolower($c);
            if (isset($usedLower[$lower])) {
                continue;
            }
            $usedLower[$lower] = true;
            $distractors[] = $c;
            if (count($distractors) >= 3) {
                break;
            }
        }

        // Pengaman jika opsi sejenis masih kurang dari 3
        if (count($distractors) < 3) {
            $safeExtras = $isPasalCorrect
                ? ['Pasal 26', 'Pasal 28A', 'Pasal 30', 'Pasal 37']
                : ($isNumericCorrect
                    ? $this->generateNumericDistractors($correctClean)
                    : [
                        'Mengabaikan kaidah inti yang berlaku pada pembahasan materi',
                        'Menjalankan langkah yang bertentangan dengan prinsip utama bacaan',
                        'Menyamakan seluruh konsep tanpa memperhatikan batasan fungsi',
                        'Mengurangi ketepatan penerapan prinsip utama bacaan',
                    ]);

            foreach ($safeExtras as $cand) {
                $c = trim((string) $cand);
                $lower = mb_strtolower($c);
                if ($c !== '' && ! isset($usedLower[$lower])) {
                    $usedLower[$lower] = true;
                    $distractors[] = $c;
                    if (count($distractors) >= 3) {
                        break;
                    }
                }
            }
        }

        $keys = ['A', 'B', 'C', 'D'];
        $correctKey = match ($sequence % 4) {
            1 => 'A',
            2 => 'B',
            3 => 'C',
            default => 'D',
        };

        $options = [];
        $dIdx = 0;
        foreach ($keys as $key) {
            $options[] = [
                'key' => $key,
                'text' => $key === $correctKey ? $correctClean : ($distractors[$dIdx++] ?? 'Pernyataan tidak sesuai dengan prinsip bacaan'),
            ];
        }

        return [
            'options' => $options,
            'correct_key' => $correctKey,
        ];
    }

    /**
     * Mencocokkan kata kunci per KATA UTUH (word boundary huruf/angka), bukan substring acak.
     */
    public function containsWholeWordTerm(string $haystack, string $needle): bool
    {
        $cleanNeedle = trim($needle);
        if ($cleanNeedle === '' || $haystack === '') {
            return false;
        }

        return preg_match('/(?<![\p{L}\p{N}])' . preg_quote($cleanNeedle, '/') . '(?![\p{L}\p{N}])/iu', $haystack) === 1;
    }

    /**
     * Mencocokkan urutan frasa kata utuh berturut-turut di dalam teks.
     *
     * @param array<int, string> $words
     */
    private function containsWholeWordPhrase(string $haystack, array $words): bool
    {
        $cleanWords = array_values(array_filter(array_map('trim', $words), fn ($w) => $w !== ''));
        if (count($cleanWords) < 2 || $haystack === '') {
            return false;
        }

        $pattern = implode('\s+', array_map(fn (string $w) => preg_quote($w, '/'), $cleanWords));

        return preg_match('/(?<![\p{L}\p{N}])' . $pattern . '(?![\p{L}\p{N}])/iu', $haystack) === 1;
    }

    /**
     * Memadatkan kalimat sumber menjadi topik ringkas untuk batang soal (tanpa menyalin paragraf utuh).
     */
    private function condenseSentenceToTopic(string $sentence, int $maxWords = 6): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', str_replace(['→', '->'], ' ', strip_tags($sentence))) ?? '');
        $clean = trim(preg_replace('/^(?:Kedudukan|Fungsi|Prinsip|Landasan|Syarat|Tujuan(?:\s+utama)?)\s*:\s*/iu', '', $clean) ?? $clean);
        $clean = preg_replace('/^(?:oleh karena itu|karena itu|dengan demikian|selain itu|namun|akan tetapi),?\s+/iu', '', $clean) ?? $clean;

        // Jika terdapat kopula (adalah/merupakan/memiliki) dengan subjek ringkas di awal, gunakan subjeknya saja agar tidak terpotong di tengah predikat
        if (preg_match('/^([^,.]{6,55}?)\s+(?:adalah|merupakan|yaitu|memiliki|menuntut|bertujuan|berfungsi)\b/iu', $clean, $subMatch)) {
            $clean = trim($subMatch[1]);
        }

        $words = preg_split('/\s+/u', trim($clean, " \t\n\r\0\x0B.,;:!?\"'“”‘’"), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) <= $maxWords) {
            return implode(' ', $words);
        }

        $sliced = array_slice($words, 0, $maxWords);
        while (count($sliced) > 2 && preg_match('/^(?:dan|atau|yang|di|ke|dari|untuk|dengan|pada|dalam|serta|agar|sehingga|karena|sebagai|mengutamakan|memadukan|melestarikan|menguasai|dilindungi|menuntut)$/iu', (string) end($sliced))) {
            array_pop($sliced);
        }

        return implode(' ', $sliced);
    }

    /**
     * Memadatkan klausa menjadi opsi jawaban ringkas dan alami (bukan tempelan paragraf panjang).
     */
    private function condenseClauseToOption(string $text, int $maxChars = 115): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', str_replace(['→', '->'], ', ', strip_tags($text))) ?? '');
        $clean = trim(preg_replace('/^(?:Kedudukan|Fungsi(?:\s+utama)?|Prinsip|Landasan|Ciri(?:\s+utama)?)\s*:\s*/iu', '', $clean) ?? $clean);
        $clean = preg_replace('/^[&+\-*\/,;:|~_–—\s]+|[&+\-*\/,;:|~_–—\s]+$/u', '', $clean) ?? $clean;
        $clean = preg_replace('/^(?:dan|atau|serta|yaitu|yakni|adalah|merupakan|bahwa|sehingga|karena|dengan demikian)\s+/iu', '', $clean) ?? $clean;
        $clean = rtrim(trim($clean, " \t\n\r\0\x0B\"'“”‘’"), '.');

        if ($clean === '') {
            return '';
        }

        $firstChar = mb_strtoupper(mb_substr($clean, 0, 1));
        $clean = $firstChar . mb_substr($clean, 1);

        if (mb_strlen($clean) <= $maxChars) {
            return $clean;
        }

        // Ambil klausa pertama yang bermakna apabila panjang melebihi batas opsi PG
        $clauses = preg_split('/(?<=.{38})[.;]\s+|,\s*(?=sehingga|agar|dengan tujuan|yang\s+(?:berfungsi|berperan))/iu', $clean, 2) ?: [];
        if (! empty($clauses[0]) && mb_strlen(trim($clauses[0])) <= $maxChars) {
            return rtrim(trim($clauses[0]), '.,;:');
        }

        $cut = mb_substr($clean, 0, $maxChars - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut), '.,;: ') . '';
    }

    /**
     * Memeriksa apakah pokok soal secara terang-terangan membocorkan isi pilihan jawaban.
     */
    public function isQuestionLeakingAnswer(string $question, string $answer): bool
    {
        $qLower = mb_strtolower($question);
        $aLower = mb_strtolower(trim($answer));
        if ($aLower === '' || mb_strlen($aLower) < 4) {
            return false;
        }
        if (str_contains($qLower, $aLower)) {
            return true;
        }
        $aWords = array_filter(
            preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', '', $aLower)) ?: [],
            fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, ['yang', 'pada', 'untuk', 'dengan', 'dalam', 'adalah', 'akan', 'atau', 'serta', 'oleh', 'dari', 'sebagai', 'mengenai', 'tentang', 'konsep', 'materi'], true)
        );
        if (count($aWords) >= 2) {
            $hits = 0;
            foreach ($aWords as $w) {
                if (str_contains($qLower, $w)) {
                    $hits++;
                }
            }
            if (($hits / count($aWords)) >= 0.7) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pemeriksaan otomatis untuk memverifikasi bahwa kunci jawaban benar-benar menjawab pertanyaan;
     * bila jawaban AI/draft menyimpang atau terlalu dangkal, perbaiki dari paragraf sumber bacaan.
     *
     * @return array{answer_key: string, reference_excerpt: string, is_valid: bool}
     */
    public function verifyAndRepairEssayPair(
        string $question,
        string $answerKey,
        string $referenceExcerpt,
        string $body,
        string $title
    ): array {
        $cleanQ = trim($question);
        $cleanKey = trim($answerKey);
        $cleanRef = trim($referenceExcerpt);

        if ($this->isQuestionAnsweredByKey($cleanQ, $cleanKey, $cleanRef)) {
            return [
                'answer_key' => $cleanKey,
                'reference_excerpt' => $cleanRef,
                'is_valid' => true,
            ];
        }

        if (trim($body) === '') {
            return [
                'answer_key' => $cleanKey,
                'reference_excerpt' => $cleanRef,
                'is_valid' => false,
            ];
        }

        $repaired = $this->recommendHeuristicAnswerForQuestion($body, $title, $cleanQ);
        $repairedKey = trim((string) ($repaired['recommended_answer'] ?? ''));
        $repairedRef = trim((string) ($repaired['reference_excerpt'] ?? ''));

        if ($repairedKey !== '' && $this->isQuestionAnsweredByKey($cleanQ, $repairedKey, $repairedRef)) {
            return [
                'answer_key' => $repairedKey,
                'reference_excerpt' => $repairedRef !== '' ? $repairedRef : $cleanRef,
                'is_valid' => true,
            ];
        }

        return [
            'answer_key' => $cleanKey !== '' ? $cleanKey : $repairedKey,
            'reference_excerpt' => $cleanRef !== '' ? $cleanRef : $repairedRef,
            'is_valid' => $cleanKey !== '',
        ];
    }

    /**
     * Memeriksa kesesuaian semantik, intent pertanyaan, dan kelengkapan kunci jawaban terhadap soal.
     */
    public function isQuestionAnsweredByKey(string $question, string $answerKey, string $referenceExcerpt = ''): bool
    {
        $cleanQ = trim($question);
        $cleanKey = trim($answerKey);

        if ($cleanQ === '' || $cleanKey === '' || mb_strlen($cleanKey) < 12) {
            return false;
        }
        if (preg_match('/^(?:Kedudukan|Fungsi|Prinsip|Landasan)\s*:/iu', $cleanKey)) {
            return false;
        }
        if (preg_match('/\b(?:ctrl\s*\+\s*x|tekan\s+y|tekan\s+enter)\b/iu', $cleanKey) && ! $this->containsWholeWordTerm($cleanQ, 'ctrl')) {
            return false;
        }

        $wordCount = count(preg_split('/\s+/u', $cleanKey, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if (preg_match('/\b(?:mengapa|evaluasi|analisis|bandingkan|dampak|alasan)\b/iu', $cleanQ) && $wordCount < 6) {
            return false;
        }
        if (preg_match('/\b(?:pasal|uud|dasar hukum)\b/iu', $cleanQ) && ! preg_match('/\b(?:pasal|uud|undang|hukum|peraturan|ketentuan)\b/iu', $cleanKey . ' ' . $referenceExcerpt)) {
            return false;
        }

        $qTerms = $this->similarity->extractKeywords($cleanQ);
        $verbNoise = ['identifikasi', 'jelaskan', 'terapkan', 'analisis', 'evaluasi', 'bandingkan', 'uraikan', 'sebutkan', 'mengapa', 'bagaimana', 'apakah', 'manakah', 'siapakah', 'kapan', 'dimana', 'nilai', 'materi', 'bacaan', 'konsep', 'pengertian', 'makna'];
        $anchors = array_values(array_diff($qTerms, $verbNoise));

        if (! empty($anchors)) {
            $combinedAnswer = trim($cleanKey . ' ' . $referenceExcerpt);
            $ansMap = $this->similarity->expandConceptMap($this->similarity->extractKeywords($combinedAnswer), $combinedAnswer);
            $matched = 0;
            foreach ($anchors as $anc) {
                if ($this->containsWholeWordTerm($combinedAnswer, $anc) || $this->similarity->tokenMatchesConceptMap($anc, $ansMap, $combinedAnswer)) {
                    $matched++;
                }
            }

            if ($matched === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Menyaring dan menormalkan 1 butir soal PG hasil AI agar opsi unik, masuk akal, & sejenis.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function sanitizeQuizQuestionItem(array $item, string $body): ?array
    {
        $question = trim((string) ($item['question'] ?? ''));
        $options = $item['options'] ?? [];
        $correctKey = strtoupper(trim((string) ($item['correct_answer'] ?? 'A')));
        if ($question === '' || ! is_array($options) || count($options) < 4) {
            return null;
        }

        $optionMap = [];
        foreach ($options as $opt) {
            $k = strtoupper(trim((string) ($opt['key'] ?? '')));
            $t = trim((string) ($opt['text'] ?? ''));
            if ($k !== '' && $t !== '') {
                $optionMap[$k] = $t;
            }
        }

        $correctText = $optionMap[$correctKey] ?? ($optionMap['A'] ?? '');
        if ($correctText === '' || preg_match('/^(?:belum tersedia|tidak ada|kosong)$/iu', $correctText)) {
            return null;
        }

        $rawDistractors = [];
        foreach (['A', 'B', 'C', 'D'] as $k) {
            if ($k !== $correctKey && isset($optionMap[$k]) && $this->isPlausibleQuizOption($optionMap[$k], $correctText, $question)) {
                $rawDistractors[] = $optionMap[$k];
            }
        }

        if (count($rawDistractors) < 3) {
            $sentences = $this->extractSubstantiveSentences($body, '');
            $fallbackTerms = $this->extractQuizKeyTerms($sentences);
            $fallbackPreds = $this->extractPredicatePool($sentences);
            $rebuilt = $this->buildUniqueQuizOptions(
                $correctText,
                $rawDistractors,
                (int) ($item['sequence'] ?? 1),
                $fallbackTerms,
                $fallbackPreds,
                $question
            );
            $item['options'] = $rebuilt['options'];
            $item['correct_answer'] = $rebuilt['correct_key'];
        }

        return $item;
    }

    /**
     * Menyaring opsi pengecoh kuis agar masuk akal, tidak bertipe beda jauh, tidak terpotong,
     * dan tidak membocorkan atau mengulang kunci jawaban.
     */
    public function isPlausibleQuizOption(string $candidate, string $correct, string $question = ''): bool
    {
        $c = trim($candidate);
        $corr = trim($correct);
        if ($c === '' || $corr === '' || mb_strlen($c) < 3) {
            return false;
        }

        $cLower = mb_strtolower($c);
        $corrLower = mb_strtolower($corr);
        if ($cLower === $corrLower) {
            return false;
        }

        // Tolak potongan kalimat/daftar pustaka/perintah kode/ujung kalimat menggantung
        if (preg_match('/(?:[&,:;]$|\b(?:dan|atau|yang|sehingga|karena|adalah|merupakan|dengan|pada|dalam|ke|dari|untuk)$|\(\d{4}\)|Balai\s+Pustaka|Raja\s+Grafindo|ctrl\s*\+\s*x|#mkdir)/iu', $c)) {
            return false;
        }

        // Tolak jika diawali simbol, tanda sambung, atau konjungsi menggantung (misal: "& ilmu pengetahuan", "dan kebudayaan")
        if (preg_match('/^[&+\-*\/,;:|~_–—]|^(?:dan|atau|serta|yaitu|yakni|adalah|merupakan|bahwa|sehingga|karena|dengan demikian)\b/iu', $c)) {
            return false;
        }

        // Anti-Bocor: Tolak jika pilihan jawaban tertulis atau dibocorkan di dalam batang soal
        if ($question !== '') {
            $qLower = mb_strtolower($question);
            if (mb_strlen($cLower) >= 6 && str_contains($qLower, $cLower)) {
                return false;
            }
            if (mb_strlen($corrLower) >= 6 && str_contains($qLower, $corrLower)) {
                return false;
            }
        }

        // Tolak jika salah satu berisi persis opsi yang lain (bocor jawaban atau duplikat makna persis)
        if (mb_strlen($cLower) >= 12 && mb_strlen($corrLower) >= 12 && (str_contains($cLower, $corrLower) || str_contains($corrLower, $cLower))) {
            return false;
        }

        // Kesetaraan kategori: Pasal vs non-Pasal, Angka vs non-Angka
        $isCorrectPasal = (bool) preg_match('/^Pasal\s+\d+/iu', $corr);
        $isCandPasal = (bool) preg_match('/^Pasal\s+\d+/iu', $c);
        if ($isCorrectPasal) {
            return $isCandPasal;
        }
        if ($isCandPasal && ! $isCorrectPasal) {
            return false;
        }

        $isCorrectNum = (bool) preg_match('/^\d+(?:[.,]\d+)?\s*[A-Za-z%]+$/iu', $corr);
        $isCandNum = (bool) preg_match('/^\d+(?:[.,]\d+)?\s*[A-Za-z%]+$/iu', $c);
        if ($isCorrectNum) {
            return $isCandNum;
        }

        // Kesetaraan proporsi panjang kata antara kunci dan pengecoh
        $corrWords = count(preg_split('/\s+/u', $corr, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $candWords = count(preg_split('/\s+/u', $c, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($corrWords <= 4 && ($candWords > 12 || mb_strlen($c) > 90)) {
            return false;
        }
        if ($corrWords >= 7 && $candWords < 3) {
            return false;
        }

        return true;
    }

    private function stripClichéPrefix(string $text): string
    {
        $clean = trim($text);
        $clean = preg_replace('/^(?:berdasarkan\s+(?:teks\s+)?(?:materi|bacaan)(?:\s+di\s+atas)?[,:\s]+|menurut\s+(?:isi\s+)?(?:materi|bacaan)(?:\s+di\s+atas)?[,:\s]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*\(Konteks:\s*[^)]+\)\s*$/iu', '', $clean) ?? $clean;
        $clean = trim($clean);

        return $clean !== '' ? mb_strtoupper(mb_substr($clean, 0, 1)) . mb_substr($clean, 1) : '';
    }

    private function decodeJsonObject(string $raw): ?array
    {
        $trimmed = trim($raw);
        if (preg_match('/\{[\s\S]*\}/u', $trimmed, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $cleanJson = preg_replace('/\s*```$/', '', (string) $cleanJson);
        $parsed = json_decode((string) $cleanJson, true);

        return is_array($parsed) ? $parsed : null;
    }

    private function decodeJsonArray(string $raw): ?array
    {
        $trimmed = trim($raw);
        if (preg_match('/\[[\s\S]*\]/u', $trimmed, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $cleanJson = preg_replace('/\s*```$/', '', (string) $cleanJson);
        $parsed = json_decode((string) $cleanJson, true);

        return is_array($parsed) ? $parsed : null;
    }
}
