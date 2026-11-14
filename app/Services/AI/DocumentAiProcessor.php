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
        $sampleText = $this->buildFullCoverageContext($formattedBody, 9000);

        $instructions = <<<INSTRUCTIONS
PENTING: LANGSUNG mulai output dengan karakter '{' tanpa salam, kata pengantar, analisis, atau monolog pembuka. Jawab HANYA dalam format JSON valid.

Kamu adalah Kurator Literasi & Asesor Berpikir Kritis Taksonomi Bloom SocratesRead AI (setara standar tinggi Challenge Socrates).
Tugasmu adalah membaca KESELURUHAN isi materi bacaan (dari paragraf awal hingga penutup) lalu menyusun intisari materi, tepat 5 soal esai penalaran kritis analitis HOTS (Bloom C3–C5), serta 10 butir soal kuis pilihan ganda berbasis skenario/studi kasus nyata.

Format output WAJIB HANYA berupa JSON valid tanpa teks lain:
{
  "excerpt": "Ringkasan intisari materi 2-3 kalimat yang padat dan jelas...",
  "questions": [
    {
      "s": "C4 Menganalisis",
      "d": "easy",
      "q": "Pertanyaan esai analitis mendalam (Bloom C3–C5) tanpa awalan klise 'Berdasarkan teks...'",
      "c": "Konteks singkat atau fokus pemikiran yang membantu siswa mengarahkan jawaban",
      "h": "Kunci jawaban model yang komprehensif, akurat, dan substantif (2-4 kalimat) yang benar-benar menjawab tuntas seluruh aspek pertanyaan sesuai fakta materi.",
      "r": "Kutipan kalimat asli dari teks materi yang menjadi rujukan bukti langsung kunci jawaban."
    }
  ],
  "quiz_questions": [
    {
      "q": "Pertanyaan PG berbobot kritis (skenario kasus / perbandingan konsep / evaluasi keputusan / analisis aturan) tanpa menyalin kalimat mentah?",
      "a": "Pilihan A (ringkas, satu kategori, masuk akal)",
      "b": "Pilihan B (ringkas, satu kategori, masuk akal)",
      "c": "Pilihan C (ringkas, satu kategori, masuk akal)",
      "d": "Pilihan D (ringkas, satu kategori, masuk akal)",
      "k": "A",
      "e": "Penjelasan lengkap ringkas 1-2 kalimat MENGAPA pilihan tersebut benar berdasarkan fakta materi."
    }
  ]
}

ATURAN KETAT:
1. questions: Wajib tepat 5 butir esai analitis HOTS Taksonomi Bloom C3 sampai C5 (urutan: Soal 1 C3 mudah, Soal 2 C4 sedang, Soal 3 C4 sedang, Soal 4 C4/C5 sulit, Soal 5 C5 sulit evaluasi).
   - DILARANG membuat soal hafalan definisi dangkal ("Jelaskan konsep utama...", "Apa pengertian...").
   - Soal harus menguji pemahaman mendalam, korelasi antar-bagian, implikasi kaidah, atau pemecahan dilema.
   - Field "h" (kunci jawaban) WAJIB berupa uraian jawaban model yang lengkap dan logis, BUKAN daftar pustaka, nama pengarang, atau instruksi guru.
2. quiz_questions: Wajib 10 butir pilihan ganda berjenjang Taksonomi Bloom C1 sampai C5 (termasuk minimal 2 soal C5 Mengevaluasi):
   - Soal harus menantang penalaran kritis: sajikan kasus nyata, evaluasi kesalahan penerapan, atau analisis komparasi.
   - DILARANG menyalin kalimat mentah dari materi ke soal atau opsi pilihan.
   - Keempat opsi (a, b, c, d) wajib berada dalam satu kelompok kategori yang seimbang, masuk akal, dan menggunakan Bahasa Indonesia baku yang wajar (DILARANG potongan kata rusak/tabel).
3. Kunci 'k' adalah tepat satu huruf: A, B, C, atau D.
4. ATURAN WAJIB SOAL BERBASIS KASUS / CERITA / PARAGRAF:
   - DILARANG KERAS menggunakan kata rujukan buta seperti "pada teks di atas", "dalam cerita di atas", atau "pada bacaan ini" tanpa menyertakan teksnya.
   - Jika pertanyaan menganalisis penggalan teks, cerita, draf yang salah, atau kasus tertentu: KUTIPAN TEKS TERSEBUT WAJIB DISERTAKAN LANGSUNG di dalam kalimat pertanyaan (misal: 'Perhatikan cuplikan berikut: "[kutipan]". Berdasarkan cuplikan tersebut, analisislah...') atau dituliskan lengkap pada field "c" (context) agar soal berdiri sendiri (self-contained) dan jelas bagi siswa.
   - Jika materi Matematika / Sains, gunakan notasi rumus LaTeX standar ($...$) agar sistem merender simbol matematika secara rapi.
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nMata Pelajaran/Kategori: {$category}\n\nTeks Materi Lengkap:\n{$sampleText}",
            ],
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.15,
            'max_output_tokens' => 3800,
            'timeout' => 90,
        ]);

        $parsed = $this->decodeJsonObject($rawResponse);
        if (! is_array($parsed) || (empty($parsed['excerpt']) && empty($parsed['questions']) && empty($parsed['quiz_questions']))) {
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

                // Jika pertanyaan memiliki rujukan buta "di atas", sertakan cuplikan teks rujukan secara mandiri
                if (preg_match('/\b(?:teks(?:\s+laporan\s+hasil\s+observasi)?\s+di\s+atas|cerita\s+di\s+atas|bacaan\s+di\s+atas)\b/iu', $prompt) && ! str_contains($prompt, '"')) {
                    $refExcerpt = trim((string) ($validatedEssay['reference_excerpt'] ?? ''));
                    if ($refExcerpt !== '' && mb_strlen($refExcerpt) >= 30) {
                        $prompt = "Perhatikan cuplikan teks berikut:\n\"" . mb_substr($refExcerpt, 0, 220) . "...\"\n\n" . preg_replace('/\b(?:di atas)\b/iu', 'tersebut', $prompt);
                    } else {
                        $prompt = preg_replace('/\b(?:di atas)\b/iu', 'pada materi bacaan ini', $prompt) ?? $prompt;
                    }
                }

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

        $teacherChallenges = [];
        foreach ($questions as $q) {
            $diff = $q['difficulty'] ?? 'medium';
            $teacherChallenges[] = [
                'type' => 'essay',
                'question' => $q['prompt'],
                'context' => null,
                'rubric' => $q['hint'],
                'reference_excerpt' => $q['reference_excerpt'] ?? $q['hint'],
                'difficulty' => $diff,
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
            ];
        }

        return [
            'cleaned_body' => $finalBody,
            'excerpt' => (string) ($parsed['excerpt'] ?? $this->generateHeuristicExcerpt($formattedBody, $title)),
            'questions' => $questions,
            'teacher_challenges' => $teacherChallenges,
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

        $structured = $this->generateStructuredTeacherChallenges($formattedBody, $title, $category);
        if (! empty($structured)) {
            return $structured;
        }

        if ($this->languageModel->isConfigured()) {
            @set_time_limit(180);
            try {
                $sampleText = $this->buildFullCoverageContext($formattedBody, 9000);
                $instructions = <<<INSTRUCTIONS
Kamu adalah Perancang Soal Berpikir Kritis Taksonomi Bloom (C3–C5) SocratesRead AI (setara standar tinggi Challenge Socrates).
Tugasmu: baca KESELURUHAN isi materi dari awal hingga akhir, lalu buat tepat 5 rekomendasi soal ESAI penalaran kritis tinggi (Higher-Order Thinking Skills / HOTS) yang menguji pemahaman mendalam, perbandingan konsep, evaluasi aturan/konsekuensi, dan pemecahan kasus nyata.

Format output WAJIB HANYA berupa JSON array valid:
[
  {
    "bloom_level": "C3",
    "difficulty": "easy",
    "question": "Pertanyaan esai analitis mendalam yang menguji alasan mendasar atau logika aturan konsep tanpa awalan 'Berdasarkan teks...'",
    "rubric": "Kunci jawaban model yang komprehensif, akurat, dan substantif (2-4 kalimat) yang benar-benar menjawab tuntas seluruh aspek pertanyaan sesuai fakta materi.",
    "reference_excerpt": "Kutipan kalimat asli dari teks bacaan yang menjadi rujukan bukti langsung kunci jawaban."
  }
]

ATURAN WAJIB:
1. WAJIB BERJENJANG PENALARAN KRITIS HOTS (C3–C5) - TIDAK BOLEH SOAL HAFALAN/DEFINISI SEDERHANA:
   - Soal 1 — C3 (Analisis Logika Konsep & Batasan Kaidah, "easy"): Analisis mengapa kaidah inti konsep tersebut harus dipenuhi secara utuh dan dampak logisnya.
   - Soal 2 — C4 (Komparasi & Analisis Perbedaan Konsep, "medium"): Bandingkan dua konsep utama dalam materi, analisis perbedaan mendasar tujuan/strukturnya, dan tentukan konteks penggunaan yang paling tepat.
   - Soal 3 — C4 (Analisis Mekanisme, Landasan Aturan/Hukum, atau Hubungan Sebab-Akibat, "medium"): Telaah bagaimana aturan/mekanisme tertentu menjamin keberhasilan atau ketahanan sistem/budaya/kebahasaan.
   - Soal 4 — C4/C5 (Penerapan Kasus Nyata & Penyelesaian Masalah di Sekolah/Masyarakat, "hard"): Analisis situasi konkret (misalnya pergeseran budaya, era digital, atau kesalahan penerapan) dan bagaimana prinsip materi menjadi solusi.
   - Soal 5 — C5 (Evaluasi Kritis, Konsekuensi Etis, & Dilema Jangka Panjang, "hard"): Evaluasi mendalam mengenai dampak jangka panjang, tanggung jawab moral/etis, atau pertimbangan kritis di era modern/digital.
2. DILARANG MEMBUAT SOAL GENERIK/DANGKAL:
   - DILARANG menggunakan pertanyaan seperti "Jelaskan konsep utama yang dipaparkan pada bagian awal materi...", "Apa yang dimaksud dengan...", atau singkatan samar.
   - Pertanyaan harus berbobot intelektual tinggi, menantang siswa berpikir dan berargumen, persis seperti Challenge Socrates.
3. KUNCI JAWABAN (RUBRIC) HARUS AKURAT & SUBSTANTIF:
   - "rubric" adalah KUNCI JAWABAN YANG BENAR dan lengkap. DILARANG hanya menulis judul buku, nama pengarang, atau instruksi guru ("Menjelaskan pengertian...").
   - Kunci jawaban harus menguraikan penjelasan yang benar, logis, dan tuntas sesuai isi materi.
4. DILARANG memakai frasa pembuka klise seperti "Berdasarkan teks materi...", "Menurut bacaan...", dsb.
5. "reference_excerpt" WAJIB berupa kutipan kalimat asli materi yang membuktikan kebenaran kunci jawaban.
6. Keluarkan HANYA JSON array tanpa markdown/komentar lain.
7. ATURAN WAJIB SOAL BERBASIS KASUS / CERITA / PARAGRAF:
   - DILARANG MERUJUK BUTA seperti "pada teks di atas" tanpa menyertakan kutipannya.
   - Jika pertanyaan menganalisis penggalan cerita, draf yang salah, atau studi kasus: KUTIPAN KASUS/TEKS WAJIB DICANTUMKAN LANGSUNG di dalam kalimat pertanyaan atau di dalam field "context" agar soal mandiri dan jelas bagi siswa.
   - Jika materi Matematika / Sains, gunakan format rumus LaTeX baku ($...$) agar sistem merender simbol matematika secara rapi.
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

                        // Bersihkan rujukan buta "di atas" dengan menyertakan cuplikan teks langsung
                        if (preg_match('/\b(?:teks(?:\s+laporan\s+hasil\s+observasi)?\s+di\s+atas|cerita\s+di\s+atas|bacaan\s+di\s+atas)\b/iu', $q) && ! str_contains($q, '"')) {
                            $refExcerpt = trim((string) ($validated['reference_excerpt'] ?? ''));
                            if ($refExcerpt !== '' && mb_strlen($refExcerpt) >= 30) {
                                $q = "Perhatikan cuplikan teks berikut:\n\"" . mb_substr($refExcerpt, 0, 220) . "...\"\n\n" . preg_replace('/\b(?:di atas)\b/iu', 'tersebut', $q);
                            } else {
                                $q = preg_replace('/\b(?:di atas)\b/iu', 'pada materi bacaan ini', $q) ?? $q;
                            }
                        }
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
        $targetCount = min(50, max(5, $targetCount));
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
            $poolTarget = min(50, max($targetCount, $existingCount + $needed + 5));
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
        $count = min(50, max(5, $count));

        $domainQuestions = $this->generateStructuredKnowledgeQuiz($body, $title, $category, $count);
        if (! empty($domainQuestions) && count($domainQuestions) >= $count) {
            return array_slice($domainQuestions, 0, $count);
        }

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
            $plain = preg_replace('/^[\s"“”\']+|[\s"“”\']+$/u', '', $plain) ?? trim($plain);

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
     * Membangun 5 rekomendasi soal Tantangan Guru terstruktur & kritis (HOTS C3–C5)
     * berbasis domain kurikulum terverifikasi (Karya Tulis Ilmiah).
     *
     * @return array<int, array{type: string, question: string, context: null, rubric: string, reference_excerpt: string, difficulty: string, difficulty_weight: int}>|null
     */
    public function generateStructuredTeacherChallenges(string $body, string $title, ?string $category = null): ?array
    {
        $bodyLower = mb_strtolower($body);
        $titleLower = mb_strtolower($title);

        // Domain: Karya Tulis Ilmiah
        if (
            str_contains($bodyLower, 'karya tulis ilmiah')
            || str_contains($titleLower, 'karya tulis ilmiah')
            || (str_contains($bodyLower, 'karya ilmiah') && str_contains($bodyLower, 'metode ilmiah'))
        ) {
            $ktiChallenges = [
                [
                    'bloom' => 'C3',
                    'difficulty' => 'easy',
                    'question' => 'Analisis mengapa penggunaan bahasa baku sesuai kaidah PUEBI dan prinsip objektivitas menjadi syarat mutlak dalam karya tulis ilmiah, serta apa dampak logisnya apabila seorang penulis menyisipkan opini emosional tanpa bukti data?',
                    'rubric' => 'Penggunaan bahasa baku dan PUEBI menjamin ketepatan makna, konsistensi terminologi, dan komunikasi akademis yang jelas bebas dari multitafsir. Jika penulis menyisipkan opini emosional tanpa bukti data, tulisan tersebut kehilangan kredibilitas ilmiahnya, tergelincir menjadi tulisan populer subjektif, dan tidak dapat diverifikasi kebenarannya oleh komunitas akademik.',
                    'reference_excerpt' => 'Objektif–berdasarkan fakta, bukan opini semata. Logis–sesuai nalar dan penalaran yang dapat diterima. Bahasa Baku–menggunakan ejaan, diksi, dan tata bahasa Indonesia yang sesuai kaidah PUEBI.',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Bandingkan karakteristik struktur dan tujuan antara Makalah dan Artikel Jurnal Ilmiah: mengapa artikel jurnal menuntut penulisan yang jauh lebih ringkas dan fokus dibandingkan makalah tugas perkuliahan?',
                    'rubric' => 'Makalah berfokus pada pembahasan masalah terstruktur untuk tugas studi dengan format yang relatif fleksibel. Sebaliknya, artikel jurnal ilmiah dirancang khusus untuk diseminasi hasil penelitian di publikasi berkala dengan batasan halaman ketat (8–15 halaman), menuntut penulis langsung mengintisarikan tujuan, metodologi mutakhir, dan hasil temuan kritis agar efisien ditelaah oleh komunitas ilmiah internasional.',
                    'reference_excerpt' => 'Makalah–karya tulis ilmiah sederhana untuk membahas suatu masalah. Artikel Ilmiah–tulisan yang dimuat di jurnal/majalah ilmiah, singkat (8–15 hlm).',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Telaah bagaimana keterkaitan logis antara Rumusan Masalah di Bab I, data temuan di Bab IV, dan penarikan kesimpulan di Bab V menjamin keutuhan sebuah karya ilmiah!',
                    'rubric' => 'Rumusan Masalah menetapkan pertanyaan sentral yang harus dijawab. Bab IV menyajikan temuan empiris dan membahasnya dengan pisau analisis teori untuk menjawab pertanyaan tersebut secara faktual. Terakhir, Bab V merangkum jawaban tuntas tersebut dalam kesimpulan tanpa memunculkan data baru. Hubungan segitiga ini menjaga agar karya ilmiah tetap koheren, terarah, dan tidak bias.',
                    'reference_excerpt' => 'Rumusan Masalah: pertanyaan inti yang hendak dijawab. Hasil dan Pembahasan: interpretasi hasil, dikaitkan dengan teori. Kesimpulan: jawaban singkat terhadap rumusan masalah.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Evaluasi mengapa bab Metodologi Penelitian (Bab III) wajib memaparkan populasi, instrumen, dan teknik analisis secara transparan, serta risiko apa yang terjadi jika metodologi ditulis secara samar?',
                    'rubric' => 'Transparansi pada Bab Metodologi merupakan fondasi validitas ilmiah agar penelitian dapat diuji keabsahannya dan direplikasi oleh peneliti lain untuk membuktikan konsistensi hasil. Jika metodologi ditulis samar, hasil penelitian tidak dapat dipercaya, diklasifikasikan sebagai klaim sepihak tanpa dasar pengujian, dan berpotensi menimbulkan bias atau kekeliruan fatal.',
                    'reference_excerpt' => 'Bab III: Metodologi Penelitian - Menjelaskan cara penelitian dilakukan: jenis penelitian, populasi/sampel, teknik pengumpulan data, instrumen, dan analisis data.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Di era digital saat data mudah disalin, evaluasi mengapa penerapan etika sitasi ilmiah (seperti APA) dan teknik parafrase mandiri krusial untuk mencegah plagiarisme serta menjaga orisinalitas pemikiran penulis!',
                    'rubric' => 'Penerapan standar sitasi ilmiah secara jujur merupakan bentuk penghormatan tertinggi terhadap hak kekayaan intelektual peneliti terdahulu, sementara parafrase mandiri membuktikan pemahaman konseptual penulis atas rujukan tersebut. Kepatuhan pada etika sitasi membedakan riset asli yang kredibel dari tindakan plagiarisme intelektual yang merusak reputasi moral akademisi.',
                    'reference_excerpt' => 'Daftar Pustaka: semua sumber yang dikutip dalam tulisan, ditulis dengan gaya penulisan tertentu (misalnya APA). Dapat Dipertanggungjawabkan–sumber data jelas, dapat diverifikasi.',
                ],
            ];

            return array_map(fn ($item) => [
                'type' => 'essay',
                'question' => $item['question'],
                'context' => null,
                'rubric' => $item['rubric'],
                'reference_excerpt' => $item['reference_excerpt'],
                'difficulty' => $item['difficulty'],
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($item['difficulty']),
            ], $ktiChallenges);
        }

        // Domain 4: Teks Laporan Hasil Observasi / Wayang / Museum (LHO / BS)
        if (
            str_contains($bodyLower, 'laporan hasil observasi')
            || str_contains($titleLower, 'laporan hasil observasi')
            || str_contains($bodyLower, 'wayang purwa')
            || (str_contains($bodyLower, 'observasi') && str_contains($bodyLower, 'wayang'))
            || (str_contains($titleLower, 'bs') && str_contains($bodyLower, 'observasi'))
        ) {
            $lhoChallenges = [
                [
                    'bloom' => 'C3',
                    'difficulty' => 'easy',
                    'question' => 'Bagaimana cara menentukan objek observasi dan menyusun jadwal observasi secara sistematis sebelum menulis teks laporan hasil observasi, serta mengapa tahap persiapan ini krusial bagi keakuratan data?',
                    'rubric' => 'Tahap persiapan diawali dengan menetapkan objek observasi yang jelas dan spesifik, kemudian menyusun jadwal pengamatan yang terstruktur mencakup waktu, lokasi, dan instrumen pencatatan data. Persiapan ini krusial agar pengamat fokus pada indikator faktual di lapangan, menghindari data yang terlewat atau bias, serta menjamin keabsahan dan keakuratan data sebelum dituangkan ke dalam teks laporan.',
                    'reference_excerpt' => 'Sebelum menyusun teks laporan hasil observasi, kamu harus menentukan objek yang akan kamu observasi, menyusun jadwal observasi, melakukan observasi, mencatat data dan hasil observasi.',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Bandingkan struktur dan fungsi antara "Pernyataan Umum (Klasifikasi)" dengan "Deskripsi Bagian" dalam teks laporan hasil observasi: bagaimana keterkaitan logis keduanya membangun pemahaman pembaca?',
                    'rubric' => 'Pernyataan umum berfungsi membuka laporan dengan memberikan definisi, kategori luas, atau pengenalan awal objek yang diamati. Selanjutnya, deskripsi bagian menguraikan secara rinci aspek fisik, jenis-jenis, maupun karakteristik khusus objek tersebut secara faktual. Keterkaitan logis dari umum ke khusus ini membantu pembaca memahami hierarki konsep secara teratur dan sistematis.',
                    'reference_excerpt' => 'Menyajikan Laporan Hasil Observasi: Mengidentifikasi isi teks laporan hasil observasi, struktur pernyataan umum, deskripsi bagian, dan deskripsi manfaat.',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Analisis mengapa penetapan wayang oleh UNESCO sebagai "Masterpiece of Oral and Intangible Heritage of Humanity" menuntut tanggung jawab pelestarian aktif, bukan sekadar kebanggaan simbolis semata!',
                    'rubric' => 'Pengakuan UNESCO membuktikan bahwa wayang memiliki nilai adiluhung dalam seni bertutur, filsafat moral, dan warisan budaya dunia yang tak ternilai. Tanggung jawab pelestarian aktif menuntut regenerasi seniman/dalang, inovasi media pertunjukan (seperti wayang motekar dan wayang suket), serta pengenalan literasi budaya kepada generasi muda agar nilai-nilai kearifan lokal tetap hidup dan tidak punah tergerus arus modernisasi.',
                    'reference_excerpt' => 'UNESCO menetapkan wayang sebagai pertunjukan bayangan boneka tersohor dari Indonesia, sebuah warisan mahakarya dunia yang tak ternilai dalam seni bertutur.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Evaluasi dampak negatif yang terjadi apabila sebuah teks laporan hasil observasi dicemari oleh opini emosional atau data yang tidak dapat diverifikasi kebenarannya!',
                    'rubric' => 'Penyusupan opini emosional atau data spekulatif merusak esensi objektivitas laporan observasi, sehingga pembaca tersesat oleh informasi yang bias dan tidak valid. Laporan tersebut kehilangan kredibilitas ilmiahnya, gagal menjadi rujukan pengetahuan yang andal, dan berpotensi memicu kesimpulan yang keliru dalam pengambilan keputusan akademis maupun praktis.',
                    'reference_excerpt' => 'Laporan hasil observasi harus menyajikan data dan fakta objektif yang dapat dipertanggungjawabkan kebenarannya secara faktual.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Di era digital saat informasi beredar cepat tanpa filter, evaluasi bagaimana keterampilan menganalisis dan menyusun teks laporan hasil observasi membentuk nalar kritis siswa dalam menyaring hoaks!',
                    'rubric' => 'Keterampilan menyusun laporan observasi melatih siswa untuk selalu menuntut bukti empiris, memverifikasi sumber data primer di lapangan, serta memisahkan antara fakta nyata dengan klaim subjektif. Sikap skeptis terukur dan disiplin verifikasi inilah yang menjadi perisai mental utama siswa agar tidak mudah terprovokasi atau mempercayai hoaks dan disinformasi di media digital.',
                    'reference_excerpt' => 'Menunjukkan kemampuan berpikir kritis dan selektif dalam mengambil informasi penting dari bacaan atau sebuah cerita.',
                ],
            ];

            return array_map(fn ($item) => [
                'type' => 'essay',
                'question' => $item['question'],
                'context' => null,
                'rubric' => $item['rubric'],
                'reference_excerpt' => $item['reference_excerpt'],
                'difficulty' => $item['difficulty'],
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($item['difficulty']),
            ], $lhoChallenges);
        }

        // Domain 5: Matematika — Eksponen & Sifat-Sifat Perpangkatan
        if (
            (str_contains($bodyLower, 'eksponen') || str_contains($titleLower, 'eksponen') || str_contains($titleLower, 'mtk') || (isset($category) && str_contains(mb_strtolower($category), 'matematika')))
            && (str_contains($bodyLower, 'pangkat') || str_contains($bodyLower, 'perkalian berulang') || str_contains($bodyLower, 'sifat-sifat eksponen') || str_contains($bodyLower, 'fungsi eksponensial'))
        ) {
            $mtkChallenges = [
                [
                    'bloom' => 'C3',
                    'difficulty' => 'easy',
                    'question' => 'Jelaskan prinsip dasar definisi bilangan berpangkat $a^n$ ($a$ bilangan real dan $n$ bilangan bulat positif), serta analisis mengapa syarat $a \neq 0$ mutlak diperlukan ketika mendefinisikan pangkat nol ($a^0 = 1$) dan pangkat negatif ($a^{-n} = \frac{1}{a^n}$)!',
                    'rubric' => 'Eksponen $a^n$ didefinisikan sebagai perkalian berulang bilangan real $a$ sebanyak $n$ faktor ($a \times a \times \dots \times a$). Syarat $a \neq 0$ mutlak diperlukan karena operasi pembagian dengan nol tidak terdefinisi dalam matematika; jika $a = 0$, bentuk $a^0$ berasal dari $0^n / 0^n = 0/0$ yang merupakan bentuk tak tentu, dan bentuk $a^{-n} = 1/0^n = 1/0$ yang menghasilkan nilai tak terdefinisi.',
                    'reference_excerpt' => 'Jika a adalah bilangan real dan n adalah bilangan bulat positif, maka a^n menyatakan hasil kali bilangan a sebanyak n faktor dan ditulis dengan a^n. Syarat basis a bukan nol pada pangkat nol dan negatif.',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Buktikan secara runtut menggunakan konsep perkalian berulang mengapa sifat perkalian eksponen menghasilkan penjumlahan pangkat ($a^m \cdot a^n = a^{m+n}$) dan sifat pembagian menghasilkan pengurangan pangkat ($\frac{a^m}{a^n} = a^{m-n}$ untuk $a \neq 0$ dan $m > n$)!',
                    'rubric' => '$a^m$ berarti perkalian $a$ sebanyak $m$ faktor dan $a^n$ sebanyak $n$ faktor. Jika dikalikan, seluruh faktor digabungkan sehingga diperoleh total $(m + n)$ buah faktor $a$, terbukti $a^m \cdot a^n = a^{m+n}$. Pada pembagian $a^m / a^n$, terdapat $m$ faktor $a$ pada pembilang dan $n$ faktor $a$ pada penyebut; sebanyak $n$ faktor saling membagi bernilai 1, menyisakan $(m - n)$ buah faktor $a$ pada pembilang, terbukti $a^m / a^n = a^{m-n}$.',
                    'reference_excerpt' => 'Sifat-sifat eksponen: a^m . a^n = a^{m+n} dan a^m / a^n = a^{m-n}. Buktikan sifat eksponen dan amati bentuk perkalian berulang.',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Dalam studi kasus penularan virus atau penyebaran pesan hoaks di materi (misal tiap orang membagikan ke 3 orang pada fase berikutnya), analisis mengapa fenomena tersebut dimodelkan sebagai fungsi eksponensial $f(x) = 3^x$ dan bukan fungsi linier $f(x) = 3x$, serta bagaimana diagram pohon menggambarkan mekanisme penggandaan tersebut!',
                    'rubric' => 'Fenomena tersebut dimodelkan sebagai fungsi eksponensial $f(x) = 3^x$ karena setiap orang yang menerima pesan atau tertular bertindak sebagai agen baru yang melipatgandakan sebaran ke 3 orang berikutnya secara multiplikatif (fase 1: 3, fase 2: 9, fase 3: 27, fase 4: 81), bukan penambahan konstan 3 orang per fase seperti fungsi linier $f(x) = 3x$. Diagram pohon menggambarkan percabangan di mana setiap simpul melahirkan 3 cabang baru, sehingga total percabangan di tingkat ke-$x$ adalah perkalian berulang $3 \times 3 \times \dots \times 3 = 3^x$.',
                    'reference_excerpt' => 'Penyebaran virus, peredaran video yang viral, dan pertumbuhan penduduk dunia memiliki satu kesamaan yaitu setiap fenomena dapat dijelaskan oleh pertumbuhan eksponen. Diagram pohon untuk ilustrasi penyebaran pesan.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Perhatikan bentuk aljabar eksponen: $\left(\frac{2^4 \cdot 3^6}{2^3 \cdot 3^2}\right)^3$. Uraikan langkah penyelesaian paling efisien dengan menerapkan sifat-sifat eksponen, serta evaluasi kesalahan konseptual apa yang paling sering dilakukan siswa saat menyelesaikan bentuk pecahan berpangkat seperti ini!',
                    'rubric' => 'Langkah paling efisien adalah menyederhanakan basis yang sama di dalam kurung terlebih dahulu dengan sifat pembagian eksponen: $2^{4-3} \cdot 3^{6-2} = 2^1 \cdot 3^4 = 2 \cdot 81 = 162$. Kemudian terapkan sifat pemangkatan $(a \cdot b)^n = a^n \cdot b^n$: $(2^1)^3 \cdot (3^4)^3 = 2^3 \cdot 3^{12} = 8 \cdot 531.441 = 4.251.528$. Kesalahan konseptual yang sering dilakukan siswa adalah mengoperasikan pangkat antar basis yang berbeda (misalnya memaksakan operasi antara 2 dan 3), atau lupa mendistribusikan pangkat luar ke seluruh faktor di pembilang dan penyebut.',
                    'reference_excerpt' => 'Sederhanakan bentuk eksponen (2^4 . 3^6) / (2^3 . 3^2) dipangkatkan 3. Perhatikan operasi basis yang sejenis dan sifat perpangkatan.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Berdasarkan pemodelan eksponensial penyebaran informasi yang dipelajari pada materi, evaluasi bagaimana pemahaman matematis mengenai laju pertumbuhan eksponen dapat membentuk nalar kritis generasi muda dalam menyaring informasi dan memutus rantai penyebaran hoaks di media sosial!',
                    'rubric' => 'Pemahaman eksponensial membuka kesadaran matematis bahwa tindakan satu orang membagikan informasi palsu tanpa verifikasi akan berlipat ganda secara geometris ke ribuan hingga jutaan pengguna lain hanya dalam beberapa fase sebaran ($3^x$ atau $10^x$). Nalar kritis yang terbentuk mendorong siswa untuk bertanggung jawab secara digital dengan menahan diri tidak langsung membagikan pesan (sharing), melakukan cek fakta terhadap sumber primer, dan secara aktif memutus rantai eksponensial disinformasi sejak fase awal di lingkungannya.',
                    'reference_excerpt' => 'Kamu akan mencoba menganalisis data penyebaran hoaks secara mandiri. Percobaan ini akan membantumu memahami konsep eksponen. Daya tarik sebuah konten di media sosial dapat membuat penyebaran konten melonjak dengan cepat.',
                ],
            ];

            return array_map(fn ($item) => [
                'type' => 'essay',
                'question' => $item['question'],
                'context' => null,
                'rubric' => $item['rubric'],
                'reference_excerpt' => $item['reference_excerpt'],
                'difficulty' => $item['difficulty'],
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($item['difficulty']),
            ], $mtkChallenges);
        }

        // Domain 6: Matematika — Barisan dan Deret Aritmetika & Geometri (Kelas 10)
        if (
            (str_contains($bodyLower, 'barisan') && (str_contains($bodyLower, 'aritmetika') || str_contains($bodyLower, 'deret') || str_contains($bodyLower, 'geometri') || str_contains($bodyLower, 'pola bilangan')))
            || (str_contains($titleLower, 'barisan') || str_contains($titleLower, 'deret') || (isset($category) && str_contains(mb_strtolower($category), 'matematika') && (str_contains($bodyLower, 'suku ke-n') || str_contains($bodyLower, 'aritmetika'))))
        ) {
            $seqChallenges = [
                [
                    'bloom' => 'C3',
                    'difficulty' => 'easy',
                    'question' => 'Jelaskan konsep dasar barisan aritmetika dan bagaimana peran nilai beda ($b = U_n - U_{n-1}$) dalam memastikan bahwa suatu barisan bertambah atau berkurang secara teratur!',
                    'rubric' => 'Barisan aritmetika adalah susunan bilangan dengan selisih antardua suku berurutan selalu konstan. Nilai beda $b$ dihitung dengan $b = U_n - U_{n-1}$. Jika $b > 0$, barisan bertambah secara teratur (barisan naik); jika $b < 0$, barisan berkurang secara teratur (barisan turun). Nilai beda yang tetap menjamin grafik pertumbuhan barisan membentuk garis linier.',
                    'reference_excerpt' => 'Barisan bilangan U1, U2, U3, ..., Un. Selisih antara dua suku berurutan disebut beda yang dinyatakan dengan b = Un - U(n-1).',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Buktikan bagaimana rumus umum suku ke-$n$ barisan aritmetika ($U_n = a + (n - 1)b$) diturunkan langkah demi langkah dari suku pertama $a$ dan penambahan beda $b$!',
                    'rubric' => 'Penurunan rumus: Suku pertama $U_1 = a$. Suku kedua $U_2 = a + b$. Suku ketiga $U_3 = a + 2b = a + (3 - 1)b$. Suku keempat $U_4 = a + 3b = a + (4 - 1)b$. Dengan mengamati pola berulang, pada suku ke-$n$ nilai beda $b$ ditambahkan sebanyak $(n - 1)$ kali dari suku awal $a$, terbukti secara deduktif bahwa $U_n = a + (n - 1)b$.',
                    'reference_excerpt' => 'Jadi, rumus umum menentukan suku ke-n pada barisan aritmetika adalah Un = a + (n - 1)b di mana a adalah suku pertama, b adalah beda, dan n adalah nomor suku.',
                ],
                [
                    'bloom' => 'C4',
                    'difficulty' => 'medium',
                    'question' => 'Pada studi kasus gedung pertunjukan seni di materi, banyak kursi pada baris ke-1 adalah 20, baris ke-2 adalah 24, dan baris ke-3 adalah 28. Analisis cara menentukan banyak kursi pada baris ke-12 dan evaluasi keunggulan penerapan rumus barisan aritmetika dibandingkan menghitung manual!',
                    'rubric' => 'Analisis matematis: Diketahui suku pertama $a = 20$ dan beda $b = 24 - 20 = 4$. Untuk baris ke-12 ($n = 12$): $U_{12} = a + (12 - 1)b = 20 + 11(4) = 20 + 44 = 64$ kursi. Keunggulan rumus aritmetika: Menghitung kapasitas baris tinggi (misal baris ke-50 atau ke-100) secara instan, presisi tanpa resiko kekeliruan akumulasi manual, dan dapat digunakan merancang tata ruang arsitektur secara efisien.',
                    'reference_excerpt' => 'Eksplorasi Gedung Pertunjukan Seni: Baris ke-1 = 20, Baris ke-2 = 24, Baris ke-3 = 28, Baris ke-4 = 32, Baris ke-5 = 36. Pola bilangan bertambah 4 di tiap baris.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Rudi menabung dengan selisih tetap antarbulan. Jika tabungan pada bulan ke-5 adalah Rp70.000 ($a + 4b = 70.000$) dan selisih kenaikan tabungan antarbulan adalah Rp10.000 ($b = 10.000$), uraikan langkah penyelesaian untuk menentukan besar tabungan awal Rudi ($a$) dan evaluasi implikasi perencanaan keuangan dari pola tabungan aritmetika tersebut!',
                    'rubric' => 'Langkah penyelesaian: Substitusikan $b = 10.000$ ke persamaan $U_5 = a + 4b = 70.000 \implies a + 4(10.000) = 70.000 \implies a + 40.000 = 70.000 \implies a = 30.000$ rupiah tabungan awal. Evaluasi keuangan: Pola tabungan aritmetika melatih disiplin menabung dengan peningkatan target simpanan yang terukur dan realistis, sehingga penabung tidak terbebani di awal dan total tabungan terakumulasi secara pasti.',
                    'reference_excerpt' => 'Alternatif Penyelesaian: U5 = 70.000, a + (5 - 1)b = 70.000, a + 4b = 70.000. Selisih nominal uang yang ditabung antarbulan dan besar uang yang ditabung pertama kali.',
                ],
                [
                    'bloom' => 'C5',
                    'difficulty' => 'hard',
                    'question' => 'Evaluasi perbedaan mendasar antara pola barisan aritmetika ($U_n = a + (n - 1)b$) dengan barisan geometri ($U_n = a \cdot r^{n-1}$), serta berikan masing-masing 1 contoh penerapan konkret keduanya dalam memecahkan masalah kehidupan nyata!',
                    'rubric' => 'Evaluasi perbedaan: Barisan aritmetika bekerja dengan operasi penjumlahan/pengurangan konstan (beda $b$) menghasilkan pola linier. Barisan geometri bekerja dengan operasi perkalian/pembagian konstan (rasio $r$) menghasilkan pola eksponensial. Contoh aritmetika: Penambahan kapasitas susunan kursi gedung bioskop atau penambahan tabungan dengan nominal tetap bulanan. Contoh geometri: Pertumbuhan pembelahan sel bakteri setiap satuan waktu atau perhitungan bunga majemuk tabungan bank.',
                    'reference_excerpt' => 'Perbedaan barisan dan deret aritmetika dengan barisan dan deret geometri. Barisan aritmetika berkaitan dengan penambahan konstan, sedangkan barisan geometri berkaitan dengan rasio kelipatan.',
                ],
            ];

            return array_map(fn ($item) => [
                'type' => 'essay',
                'question' => $item['question'],
                'context' => null,
                'rubric' => $item['rubric'],
                'reference_excerpt' => $item['reference_excerpt'],
                'difficulty' => $item['difficulty'],
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($item['difficulty']),
            ], $seqChallenges);
        }

        return null;
    }

    /**
     * @return array<int, array{type: string, question: string, context: string, rubric: string, reference_excerpt: string, difficulty: string, difficulty_weight: int}>
     */
    public function generateHeuristicTeacherChallengeSuggestions(string $body, string $title, ?string $category = null): array
    {
        $structured = $this->generateStructuredTeacherChallenges($body, $title, $category);
        if (! empty($structured)) {
            return $structured;
        }

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

        $cleanTitle = trim($title);
        $q1Subject = '';

        // 1. Pengertian & Konsep Utama (Easy / C3)
        $s1 = $pickSentence(
            fn (string $s, int $idx) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu|dengan demikian)\b/iu', $s)
                && ((preg_match('/\b(?:adalah|merupakan|yaitu|didefinisikan|memakai AI|berbasis|konsep|strategi)\b/iu', $s) && ($idx <= 3 || $isSocratic)) || ($idx === ($isSocratic && $total > 6 ? 1 : 0) && preg_match('/\b(?:adalah|merupakan|yaitu|didefinisikan)\b/iu', $s))),
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
            $cleanTitle = trim($title);
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
                $cleanTopic = (mb_strlen($cleanTitle) <= 4 || strtoupper($cleanTitle) === $cleanTitle) ? 'materi bacaan ini' : "materi \"{$cleanTitle}\"";
                $q1Prompt = $isSocratic
                    ? "Menurut penalaran Anda, mengapa gagasan pokok pada pembukaan {$cleanTopic} krusial untuk dipahami?"
                    : $pickVariation($title . '|q1g', [
                        "Analisis prinsip mendasar yang diuraikan pada pembahasan awal {$cleanTopic}, dan mengapa pemahaman konsep tersebut krusial sebelum menerapkannya!",
                        "Mengapa kaidah pokok yang dipaparkan dalam {$cleanTopic} harus dipahami secara menyeluruh dan tidak boleh diterapkan secara parsial?",
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
     * Membangun bank soal kuis berstruktur substansial & berbasis domain materi nyata
     * untuk mencegah soal kaku, templat berulang, dan pengulangan pilihan jawaban antar-soal.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>|null
     */
    private function generateStructuredKnowledgeQuiz(string $body, string $title, ?string $category = null, int $count = 10): ?array
    {
        $bodyLower = mb_strtolower($body);
        $titleLower = mb_strtolower($title);
        $items = [];

        // Domain 1: Trigatra Bangun Bahasa (TBB)
        if (str_contains($bodyLower, 'trigatra bangun bahasa') || str_contains($titleLower, 'trigatra') || str_contains($bodyLower, 'anton moeliono')) {
            $tbbPool = [
                [
                    'bloom' => 'C1',
                    'question' => 'Siapakah tokoh kebahasaan yang pertama kali memperkenalkan konsep Trigatra Bangun Bahasa pada tahun 1985?',
                    'correct' => 'Anton Moeliono',
                    'distractors' => ['Sutan Takdir Alisjahbana', 'Gorys Keraf', 'Harimurti Kridalaksana'],
                    'explanation' => 'Konsep Trigatra Bangun Bahasa pertama kali diperkenalkan oleh pakar bahasa Anton Moeliono pada tahun 1985 melalui bukunya Pengembangan dan Pembinaan Bahasa: Trigatra Bangun Bahasa.',
                ],
                [
                    'bloom' => 'C1',
                    'question' => 'Pasal berapakah dalam UUD 1945 yang menetapkan kedudukan Bahasa Indonesia sebagai bahasa nasional dan bahasa negara?',
                    'correct' => 'UUD 1945 Pasal 36',
                    'distractors' => ['UUD 1945 Pasal 32', 'UUD 1945 Pasal 31', 'UUD 1945 Pasal 28E'],
                    'explanation' => 'UUD 1945 Pasal 36 secara tegas menetapkan Bahasa Indonesia sebagai bahasa negara dan sarana pemersatu bangsa.',
                ],
                [
                    'bloom' => 'C1',
                    'question' => 'Landasan konstitusi manakah yang menjamin perlindungan bahasa daerah sebagai bagian dari kebudayaan nasional?',
                    'correct' => 'UUD 1945 Pasal 32',
                    'distractors' => ['UUD 1945 Pasal 36', 'UUD 1945 Pasal 33', 'UUD 1945 Pasal 34'],
                    'explanation' => 'UUD 1945 Pasal 32 mewajibkan negara menghormati dan memelihara bahasa daerah sebagai kekayaan budaya nasional.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Bagaimanakah rumusan semboyan atau prinsip utama dalam Trigatra Bangun Bahasa?',
                    'correct' => 'Mengutamakan bahasa Indonesia, melestarikan bahasa daerah, menguasai bahasa asing',
                    'distractors' => [
                        'Mengutamakan bahasa daerah, mempelajari bahasa Indonesia, membatasi bahasa asing',
                        'Menguasai bahasa asing terlebih dahulu, baru memperkuat bahasa daerah dan nasional',
                        'Menyamakan kedudukan ketiga bahasa tanpa aturan prioritas penggunaan di ruang publik',
                    ],
                    'explanation' => 'Semboyan resmi Trigatra Bangun Bahasa menuntut keseimbangan: mengutamakan bahasa nasional, melestarikan bahasa daerah, dan menguasai bahasa asing.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Dalam penataan informasi publik dwibahasa, bagaimana aturan penempatan Bahasa Indonesia menurut prinsip "Utamakan Bahasa Indonesia"?',
                    'correct' => 'Diletakkan pada posisi paling atas, kemudian baru diikuti terjemahan bahasa lainnya di bawahnya',
                    'distractors' => [
                        'Diletakkan di bagian paling bawah sebagai catatan kaki pelengkap informasi',
                        'Ditulis berdampingan dengan ukuran huruf yang sama tanpa hierarki posisi',
                        'Hanya dicantumkan apabila tidak ada padanan kata dalam bahasa internasional',
                    ],
                    'explanation' => 'Penataan ruang publik menuntut Bahasa Indonesia berada di posisi paling atas guna menegaskan kedaulatan dan martabat bahasa negara.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Analisis makna penting dari arahan "Lestarikan bahasa daerah" dalam kerangka kebudayaan nasional adalah...',
                    'correct' => 'Menjaga keberlangsungan tradisi, sastra, dan kearifan lokal agar tidak punah tergerus zaman',
                    'distractors' => [
                        'Menjadikan bahasa daerah sebagai bahasa pengantar wajib sidang formal kenegaraan',
                        'Membatasi masyarakat daerah agar tidak mempelajari bahasa asing dan teknologi luar',
                        'Menggantikan seluruh kosakata bahasa nasional dengan ragam dialek lokal',
                    ],
                    'explanation' => 'Kata "Lestarikan" bermakna merawat tradisi dan sastra lokal agar kekayaan identitas kedaerahan tidak hilang ditelan zaman.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Berdasarkan materi, mengapa penguasaan setidaknya satu bahasa asing sangat ditekankan di era digital saat ini?',
                    'correct' => 'Untuk membuka akses ilmu pengetahuan, teknologi modern, dan mampu bersaing di tingkat global',
                    'distractors' => [
                        'Untuk menggantikan fungsi bahasa nasional dalam komunikasi formal pemerintahan',
                        'Agar tradisi kebudayaan daerah dapat ditinggalkan demi modernitas instan',
                        'Untuk membatasi pergaulan warga hanya pada komunitas ekspatriat',
                    ],
                    'explanation' => 'Bahasa asing diposisikan sebagai jembatan kompetensi global untuk menyerap sains dan teknologi dunia.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Dalam ranah manakah bahasa daerah paling tepat difungsikan dan diprioritaskan penggunaannya?',
                    'correct' => 'Percakapan kekeluargaan di daerah, pementasan seni tradisional, dan upacara adat',
                    'distractors' => [
                        'Penulisan naskah undang-undang perdata dan sidang resmi kenegaraan',
                        'Publikasi artikel jurnal ilmiah bereputasi internasional',
                        'Komunikasi diplomasi antarpemerintah negara anggota PBB',
                    ],
                    'explanation' => 'Bahasa daerah hidup subur pada ranah keluarga, pergaulan lokal, kesenian tradisional, dan upacara adat masyarakat.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Evaluasi pernyataan manakah yang paling tepat menilai peran Bahasa Indonesia sebagai lambang identitas nasional?',
                    'correct' => 'Sebagai alat pemersatu keberagaman suku bangsa dan sarana komunikasi resmi kenegaraan',
                    'distractors' => [
                        'Sebagai sarana komunikasi tertutup khusus untuk instansi militer dan intelijen',
                        'Sebagai bahasa yang hanya boleh dipakai saat upacara kemerdekaan saja',
                        'Sebagai pengganti seluruh dialek lokal di lingkungan keluarga pedesaan',
                    ],
                    'explanation' => 'Bahasa Indonesia mengatasi sekat-sekat etnis sehingga menjadi wadah persatuan yang inklusif bagi seluruh warga bangsa.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Evaluasi kesimpulan manakah yang paling tepat mengenai esensi konsep Trigatra Bangun Bahasa dalam pembangunan bangsa?',
                    'correct' => 'Strategi kebahasaan terpadu yang memadukan peran proporsional bahasa Indonesia, daerah, dan asing',
                    'distractors' => [
                        'Kebijakan yang memprioritaskan bahasa asing di atas kepentingan bahasa nasional',
                        'Program pelarangan bahasa daerah demi percepatan integrasi nasional',
                        'Upaya penyeragaman seluruh dialek bahasa Nusantara menjadi satu bahasa baku',
                    ],
                    'explanation' => 'Trigatra Bangun Bahasa adalah strategi sinergis agar ketiga ranah bahasa berkembang harmonis sesuai proporsinya.',
                ],
            ];
            $items = $tbbPool;
        }

        // Domain 2: Ringkasan, Rangkuman, & Ikhtisar (RRI)
        if (str_contains($bodyLower, 'ringkasan') && (str_contains($bodyLower, 'rangkuman') || str_contains($bodyLower, 'ikhtisar'))) {
            $rriPool = [
                [
                    'bloom' => 'C2',
                    'question' => 'Karakteristik utama yang membedakan penulisan ringkasan dibandingkan rangkuman dan ikhtisar adalah...',
                    'correct' => 'Wajib mempertahankan urutan isi dan alur pokok teks aslinya secara runtut',
                    'distractors' => [
                        'Boleh menyusun ulang pokok pikiran secara bebas tanpa terikat urutan asli',
                        'Wajib memasukkan pandangan dan tafsiran pribadi penulis',
                        'Menggunakan gaya bahasa puitis dan metafora yang bebas',
                    ],
                    'explanation' => 'Ciri khas mutlak sebuah ringkasan adalah menyajikan kembali teks secara singkat dengan tetap mengikuti alur dan urutan gagasan utama teks aslinya.',
                ],
                [
                    'bloom' => 'C1',
                    'question' => 'Berdasarkan materi, bentuk penyajian singkat manakah yang memperbolehkan adanya unsur interpretasi atau pendapat pribadi penulis?',
                    'correct' => 'Ikhtisar',
                    'distractors' => ['Ringkasan', 'Rangkuman', 'Transkrip'],
                    'explanation' => 'Berbeda dengan ringkasan dan rangkuman yang bersifat objektif, ikhtisar bersifat subjektif dan boleh memuat tanggapan atau penilaian pribadi penulis.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Dalam pembuatan rangkuman, bagaimanakah aturan mengenai susunan urutan pokok-pokok pikiran dari teks sumber?',
                    'correct' => 'Boleh disusun ulang secara logis dan tidak harus mengikuti urutan teks aslinya secara ketat',
                    'distractors' => [
                        'Harus persis sama urutannya dari paragraf pertama hingga terakhir tanpa perubahan',
                        'Hanya boleh mengambil kutipan kalimat dari paragraf pembuka saja',
                        'Wajib mengurutkan gagasan berdasarkan abjad judul bab',
                    ],
                    'explanation' => 'Rangkuman berfokus pada inti ide terpenting dan fleksibel disusun ulang secara logis untuk merangkum bagian yang panjang.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Menghimpun poin penting dari beberapa bab buku kuliah menjadi satu naskah padat menghasilkan bentuk...',
                    'correct' => 'Rangkuman',
                    'distractors' => ['Ringkasan', 'Ikhtisar', 'Parafrasa bebas'],
                    'explanation' => 'Mengambil intisari dari beberapa bab atau beberapa sumber bacaan yang berbeda merupakan karakteristik khas dari rangkuman.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Dari aspek sifat bahasa dan keterikatan pada opini, manakah perbedaan mendasar antara ringkasan dan ikhtisar?',
                    'correct' => 'Ringkasan bersifat objektif tanpa opini pribadi, sedangkan ikhtisar bersifat subjektif dan memuat penafsiran penulis',
                    'distractors' => [
                        'Ringkasan bersifat subjektif dan penuh opini, sedangkan ikhtisar harus objektif murni',
                        'Keduanya sama-sama melarang adanya sudut pandang atau opini dari penulis',
                        'Ringkasan tidak terikat urutan teks asli, sedangkan ikhtisar terikat ketat pada urutan teks asli',
                    ],
                    'explanation' => 'Ringkasan setia pada fakta teks sumber tanpa opini, sementara ikhtisar menonjolkan sudut pandang dan interpretasi penulis.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Apa tujuan pokok dari pembuatan ringkasan menurut penjelasan materi?',
                    'correct' => 'Untuk memahami keseluruhan isi bacaan secara cepat dengan mempertahankan alur gagasan pokok',
                    'distractors' => [
                        'Untuk mengkritik kelemahan argumen yang disampaikan oleh penulis asli',
                        'Untuk menambahkan ide-ide baru yang belum ada di dalam naskah sumber',
                        'Untuk memperpanjang uraian teks dengan analogi tambahan',
                    ],
                    'explanation' => 'Ringkasan bertujuan mempermudah pembaca menangkap intisari keseluruhan teks sumber secara cepat dan akurat.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Langkah awal yang paling tepat dilakukan dalam prosedur penulisan ringkasan yang baik adalah...',
                    'correct' => 'Membaca teks asli secara saksama dan menandai kalimat utama setiap paragraf',
                    'distractors' => [
                        'Langsung menulis kesimpulan akhir tanpa membaca seluruh naskah',
                        'Mengubah sudut pandang orang pertama menjadi orang ketiga',
                        'Menghapus seluruh contoh tanpa mencatat gagasan penopangnya',
                    ],
                    'explanation' => 'Membaca teks sumber dengan teliti dan menemukan ide pokok tiap paragraf adalah fondasi sebelum membuat kerangka ringkasan.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Analisis perbandingan sumber rujukan: manakah yang paling tepat membedakan sumber teks ringkasan dan rangkuman?',
                    'correct' => 'Ringkasan umumnya bertumpu pada satu teks utuh, sedangkan rangkuman dapat bersumber dari beberapa teks sekaligus',
                    'distractors' => [
                        'Ringkasan selalu bersumber dari banyak buku, sedangkan rangkuman hanya dari satu paragraf',
                        'Rangkuman hanya boleh dibuat dari sumber lisan, sedangkan ringkasan dari sumber tulisan',
                        'Tidak ada perbedaan sumber rujukan karena keduanya hanya boleh menggunakan satu kalimat',
                    ],
                    'explanation' => 'Sesuai tabel perbandingan, ringkasan biasanya merujuk pada satu teks sumber, sedangkan rangkuman dapat menghimpun informasi dari beberapa bab atau teks.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Evaluasi manakah yang paling tepat menilai kaidah penggunaan bahasa sendiri dalam penulisan ringkasan?',
                    'correct' => 'Menggunakan susunan kalimat sendiri secara padat tanpa mengubah makna dan urutan gagasan asli',
                    'distractors' => [
                        'Menyalin kata demi kata dari teks asli tanpa ada perubahan kalimat sama sekali',
                        'Mengganti seluruh istilah teknis dengan kata-kata buatan sendiri yang maknanya berbeda',
                        'Memasukkan penilaian apakah isi teks tersebut benar atau salah menurut selera pembaca',
                    ],
                    'explanation' => 'Prinsip penulisan ringkasan menuntut keterampilan memparafrasakan dengan kata sendiri namun tetap setia pada substansi dan makna asli naskah.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Jika sebuah teks terdiri dari 5 paragraf diringkas menjadi 1–2 paragraf, tolok ukur keberhasilan ringkasan tersebut adalah...',
                    'correct' => 'Seluruh gagasan utama tetap tersampaikan secara utuh dengan alur yang tetap runtut',
                    'distractors' => [
                        'Semua kata sulit berhasil dihilangkan dari tulisan',
                        'Panjang tulisan bertambah dengan adanya komentar pembaca',
                        'Urutan peristiwa dibalik untuk menarik perhatian pembaca',
                    ],
                    'explanation' => 'Ringkasan yang baik berhasil memadatkan volume bacaan secara drastis tanpa meninggalkan poin penting atau merusak urutan pesan sumber.',
                ],
                [
                    'bloom' => 'C1',
                    'question' => 'Berdasarkan materi, aspek manakah dari ketiga bentuk penyajian singkat yang memiliki gaya bahasa paling bebas?',
                    'correct' => 'Ikhtisar',
                    'distractors' => ['Ringkasan', 'Rangkuman', 'Transkrip naskah'],
                    'explanation' => 'Sesuai tabel perbandingan ciri bahasa, ikhtisar memiliki bahasa subjektif dengan gaya bahasa yang bebas sesuai logika dan interpretasi penulis.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Mengapa dalam penulisan ringkasan seorang penulis dilarang keras memasukkan pendapat atau penilaian pribadinya?',
                    'correct' => 'Karena ringkasan berfungsi menyampaikan kembali gagasan penulis asli secara murni dan objektif',
                    'distractors' => [
                        'Karena pendapat pribadi akan selalu memperindah gaya bahasa naskah',
                        'Karena pembaca ringkasan tidak memerlukan informasi yang akurat',
                        'Karena naskah asli sudah pasti tidak memiliki kelemahan argumen',
                    ],
                    'explanation' => 'Ringkasan bersifat objektif, sehingga tugas penyusun hanyalah memadatkan isi asli pengarang tanpa menyisipkan opini sendiri.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Setelah menandai kalimat utama pada setiap paragraf teks sumber, langkah berikutnya dalam menyusun ringkasan adalah...',
                    'correct' => 'Membuat kerangka isi berdasarkan urutan gagasan utama yang telah ditandai',
                    'distractors' => [
                        'Langsung menerbitkan tulisan tanpa memeriksa kembali alur teks',
                        'Menghapus semua paragraf yang mengandung data statistik',
                        'Mengubah judul naskah dengan istilah yang kontroversial',
                    ],
                    'explanation' => 'Langkah ke-3 dalam prosedur penulisan ringkasan adalah menyusun kerangka isi mengikuti urutan gagasan utama teks asli.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Dalam menyusun rangkuman dari beberapa bagian teks panjang, prosedur yang tepat adalah...',
                    'correct' => 'Mencatat ide-ide penting dari setiap bagian lalu menggabungkannya menjadi poin-poin inti secara logis',
                    'distractors' => [
                        'Menyalin paragraf pembuka dari setiap artikel secara berurutan',
                        'Menghitung persentase kemiripan kata antarnaskah artikel',
                        'Mengabaikan ide pokok dan hanya menuliskan daftar pustaka',
                    ],
                    'explanation' => 'Prosedur menulis rangkuman diawali dengan mencatat ide penting tiap bagian lalu mengintegrasikannya menjadi poin inti yang logis.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Apa perbedaan mendasar antara rangkuman dan ikhtisar dalam hal kehadiran unsur tafsiran atau opini?',
                    'correct' => 'Rangkuman tidak memuat unsur opini sama sekali, sedangkan ikhtisar membolehkan adanya tafsiran penulis',
                    'distractors' => [
                        'Rangkuman selalu penuh dengan opini pribadi, sedangkan ikhtisar bebas dari opini',
                        'Keduanya wajib menyertakan kritik tajam terhadap pengarang buku',
                        'Tidak ada perbedaan karena keduanya sama-sama berisi spekulasi penulis',
                    ],
                    'explanation' => 'Tabel perbandingan materi menegaskan bahwa unsur pendapat tidak ada pada rangkuman, tetapi ada pada ikhtisar.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Bagaimanakah ciri panjang tulisan pada ikhtisar jika dibandingkan dengan ringkasan dan rangkuman?',
                    'correct' => 'Panjang ikhtisar lebih bebas dan proporsional sesuai kebutuhan penafsiran penulis',
                    'distractors' => [
                        'Panjang ikhtisar wajib tepat separuh dari jumlah kata naskah sumber',
                        'Ikhtisar harus selalu lebih panjang daripada buku sumber aslinya',
                        'Ikhtisar dibatasi secara mutlak maksimal hanya satu kalimat saja',
                    ],
                    'explanation' => 'Pada aspek panjang tulisan, ringkasan berciri singkat, rangkuman lebih padat, sedangkan ikhtisar bersifat lebih bebas sesuai kedalaman ulasan.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Jika pembaca menyusun ulasan singkat dan menyertakan tanggapan pribadi "Saya setuju...", bentuk tulisan tersebut tergolong...',
                    'correct' => 'Ikhtisar',
                    'distractors' => ['Ringkasan', 'Rangkuman', 'Naskah transkrip'],
                    'explanation' => 'Adanya unsur persetujuan dan sudut pandang pribadi ("Saya setuju...") mencerminkan sifat subjektif dan interpretatif khas ikhtisar.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Evaluasi manakah yang paling tepat menilai kelemahan fatal dari sebuah ringkasan yang sengaja mengubah urutan gagasan teks aslinya?',
                    'correct' => 'Ringkasan tersebut menyalahi prinsip utama karena gagal mempertahankan keruntutan alur naskah sumber',
                    'distractors' => [
                        'Ringkasan tersebut dinilai lebih kreatif karena berani merombak struktur teks',
                        'Perubahan urutan diwajibkan agar pembaca tidak merasa bosan',
                        'Ringkasan tidak memiliki aturan urutan sehingga bebas diacak',
                    ],
                    'explanation' => 'Ciri pokok ringkasan adalah wajib mempertahankan alur dan hierarki urutan gagasan asli dari awal hingga akhir.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Pada langkah terakhir penulisan ringkasan, mengapa penulis wajib memeriksa kembali draf ringkasannya terhadap teks sumber?',
                    'correct' => 'Untuk memastikan kesesuaian informasi dan menjaga agar tidak terjadi perubahan makna dari gagasan asli',
                    'distractors' => [
                        'Untuk menambahkan kosakata asing sebanyak mungkin ke dalam teks',
                        'Untuk memperbanyak jumlah halaman agar memenuhi syarat minimal',
                        'Untuk mencari celah kesalahan tata bahasa pengarang asli',
                    ],
                    'explanation' => 'Langkah ke-5 penulisan ringkasan adalah memeriksa kesesuaian draf dengan teks sumber guna menjamin integritas makna naskah.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Dalam konteks berpikir kritis, kemampuan selektif dalam menyusun rangkuman tercermin dari tindakan...',
                    'correct' => 'Memilah gagasan utama yang esensial dan mengesampingkan rincian penjelasan atau ilustrasi tambahan',
                    'distractors' => [
                        'Menghapus seluruh data fakta dan hanya menyimpan opini penulis',
                        'Menghafal seluruh naskah tanpa melakukan seleksi informasi',
                        'Mengambil kutipan secara acak tanpa memahami keterkaitan maknanya',
                    ],
                    'explanation' => 'Berpikir kritis dalam merangkum diwujudkan melalui kemampuan memilah mana ide pokok yang mendasar dan mana rincian penjelas yang dapat diabaikan.',
                ],
            ];
            $items = $rriPool;
        }

        // Domain 3: Karya Tulis Ilmiah (KTI)
        if (
            str_contains($bodyLower, 'karya tulis ilmiah')
            || str_contains($titleLower, 'karya tulis ilmiah')
            || (str_contains($bodyLower, 'karya ilmiah') && str_contains($bodyLower, 'metode ilmiah'))
        ) {
            $ktiPool = [
                [
                    'bloom' => 'C1',
                    'question' => 'Dalam penulisan karya tulis ilmiah, mengapa penggunaan ragam bahasa baku dan ejaan yang disempurnakan (PUEBI) bersifat mutlak?',
                    'correct' => 'Untuk menjamin kejelasan makna, objektivitas gagasan, dan menghindari multitafsir bagi pembaca akademis',
                    'distractors' => [
                        'Agar tulisan terlihat rumit dan hanya dapat dipahami oleh kalangan pakar tertentu',
                        'Untuk menggantikan seluruh data kuantitatif penelitian dengan istilah sastra',
                        'Hanya sebagai formalitas administratif tanpa memengaruhi bobot substansi naskah',
                    ],
                    'explanation' => 'Bahasa baku dan kaidah PUEBI dalam karya ilmiah menjamin penyampaian gagasan yang objektif, presisi, logis, dan bebas dari ambiguitas.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Sebuah abstrak karya tulis ilmiah memuat intisari penting dalam rentang 150–250 kata. Komponen pokok apa saja yang wajib dirangkum di dalamnya?',
                    'correct' => 'Tujuan penelitian, metode yang digunakan, temuan atau hasil utama, serta kata kunci',
                    'distractors' => [
                        'Latar belakang panjang, biodata lengkap peneliti, dan daftar seluruh pustaka acuan',
                        'Ucapan terima kasih kepada penyandang dana serta surat pengesahan institusi',
                        'Daftar tabel lampiran beserta dokumentasi foto kegiatan penelitian lapangan',
                    ],
                    'explanation' => 'Abstrak merupakan miniatur karya ilmiah yang merangkum empat pilar inti: tujuan, metodologi penelitian, hasil utama yang diperoleh, dan kata kunci.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Analisis hubungan struktural pada Bab I: mengapa setiap butir tujuan penelitian wajib berkorespondensi langsung dengan rumusan masalah?',
                    'correct' => 'Karena tujuan penelitian merupakan pernyataan operasional untuk menjawab pertanyaan yang diajukan dalam rumusan masalah',
                    'distractors' => [
                        'Agar jumlah halaman pada bab pendahuluan seimbang dengan bab pembahasan',
                        'Karena rumusan masalah hanya berupa formalitas yang tidak perlu dibuktikan jawabannya',
                        'Untuk memperluas cakupan kajian agar membahas topik di luar rumusan yang telah ditetapkan',
                    ],
                    'explanation' => 'Rumusan masalah merumuskan pertanyaan fokus penelitian, sedangkan tujuan penulisan menetapkan target capaian untuk menjawab tuntas pertanyaan tersebut.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Dalam penulisan ilmiah yang bermutu, bagaimanakah posisi teori pada Bab II saat digunakan pada Bab IV (Hasil dan Pembahasan)?',
                    'correct' => 'Teori difungsikan sebagai pisau analisis dan pembanding untuk menerangkan makna di balik data temuan lapangan',
                    'distractors' => [
                        'Teori disalin ulang secara utuh untuk memperpanjang volume naskah laporan',
                        'Teori diabaikan sepenuhnya karena data lapangan dianggap sudah dapat menjelaskan dirinya sendiri',
                        'Teori dipakai untuk mengubah data lapangan yang tidak sesuai agar tampak sempurna',
                    ],
                    'explanation' => 'Bab II menyediakan landasan teoretis yang menjadi instrumen kritis bagi peneliti untuk membedah, menafsirkan, dan mengonfirmasi hasil temuan di Bab IV.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Berdasarkan karakteristik jenis karya ilmiah, manakah analisis komparasi yang paling tepat antara makalah dan artikel jurnal ilmiah?',
                    'correct' => 'Makalah berfokus pada pembahasan masalah praktis tugas studi, sedangkan artikel jurnal dirancang ringkas (8–15 hlm) dengan penelaahan kritis untuk diseminasikan ke komunitas ilmiah',
                    'distractors' => [
                        'Makalah selalu memuat temuan teori baru, sedangkan artikel jurnal hanya berisi opini bebas tanpa metode',
                        'Artikel jurnal memiliki halaman lebih tebal dari skripsi dan tidak membutuhkan abstrak',
                        'Keduanya tidak memiliki perbedaan karena sama-sama disusun tanpa kaidah metodologi penelitian',
                    ],
                    'explanation' => 'Makalah umumnya menyajikan telaah tugas akademik sederhana, sedangkan artikel jurnal ilmiah memiliki struktur padat (8-15 halaman) dengan metode dan hasil terfokus untuk publikasi berkala ilmiah.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Seorang peneliti memasukkan temuan statistik baru di Bab V (Kesimpulan) yang belum pernah dianalisis pada Bab IV. Evaluasi paling kritis terhadap tindakan tersebut adalah...',
                    'correct' => 'Tidak tepat, karena kesimpulan hanya boleh menyarikan jawaban atas rumusan masalah berdasarkan analisis yang telah tuntas di Bab IV',
                    'distractors' => [
                        'Tepat, agar bab kesimpulan memberikan kejutan informasi baru bagi pembaca',
                        'Tepat, asalkan data statistik tersebut diperoleh dari sumber primer terpercaya',
                        'Tidak menjadi masalah karena bab penutup bebas diisi informasi tambahan apa pun',
                    ],
                    'explanation' => 'Prinsip integritas karya ilmiah melarang adanya temuan atau data baru di Bab Kesimpulan; kesimpulan wajib merupakan kristalisasi dari pembahasan Bab IV.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Mengapa kejelasan rincian jenis penelitian, populasi/sampel, instrumen, dan teknik analisis pada Bab III menjadi penentu utama kredibilitas karya ilmiah?',
                    'correct' => 'Agar prosedur penelitian dapat diuji ulang (replikasi) dan diverifikasi keabsahan datanya oleh peneliti lain',
                    'distractors' => [
                        'Untuk membuktikan bahwa penelitian menghabiskan biaya operasional yang besar',
                        'Agar pembaca umum kesulitan meniru hasil penelitian yang telah dipublikasikan',
                        'Supaya instrumen penelitian tidak perlu diuji validitas dan reliabilitasnya',
                    ],
                    'explanation' => 'Sifat dapat dipertanggungjawabkan dan objektif menuntut Bab Metodologi transparan sehingga siapa pun dapat memverifikasi dan mereplikasi langkah penelitian secara objektif.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Dalam perumusan manfaat di Bab I, bagaimana membedakan penerapan manfaat teoretis dan manfaat praktis secara tepat?',
                    'correct' => 'Manfaat teoretis ditujukan bagi pengembangan khazanah ilmu pengetahuan, sedangkan manfaat praktis memberikan solusi pemecahan masalah bagi instansi atau masyarakat',
                    'distractors' => [
                        'Manfaat teoretis ditujukan untuk menghasilkan keuntungan finansial, sedangkan manfaat praktis untuk teori akademis',
                        'Manfaat teoretis hanya berlaku bagi penulis, sedangkan manfaat praktis hanya bagi dosen pembimbing',
                        'Keduanya sama-sama tidak memberikan kegunaan nyata bagi pembaca maupun masyarakat',
                    ],
                    'explanation' => 'Manfaat teoretis berorientasi pada kontribusi keilmuan akademis, sementara manfaat praktis menyasar kemaslahatan terapan bagi masyarakat atau pihak terkait.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Di era kemudahan akses internet dan AI, evaluasi tindakan manakah yang mencerminkan integritas ilmiah tertinggi dalam mengutip rujukan?',
                    'correct' => 'Menyertakan sitasi sumber asli secara jujur menggunakan format baku (misalnya APA/IEEE) serta memparafrasakan ide dengan gaya bahasa sendiri',
                    'distractors' => [
                        'Menyalin paragraf utuh dari web tanpa kutipan asalkan diterjemahkan ke bahasa daerah',
                        'Menggunakan kembali hasil karya orang lain dengan hanya mengubah nama penulis pada halaman judul',
                        'Mengutip hanya dari blog pribadi tanpa memeriksa kredibilitas sumber rujukan aslinya',
                    ],
                    'explanation' => 'Integritas ilmiah dijaga melalui kejujuran sitasi, pengakuan atas hak cipta penulis sumber dengan standar sitasi ilmiah (seperti APA), dan kemampuan parafrase yang bertanggung jawab.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Seorang siswa menulis karya ilmiah hanya bertumpu pada postingan media sosial dan blog pribadi tanpa artikel jurnal ilmiah. Evaluasi kritis terhadap naskah tersebut adalah...',
                    'correct' => 'Karya tersebut lemah dalam validitas ilmiah karena tidak memanfaatkan sumber primer atau data terverifikasi yang dapat dipertanggungjawabkan',
                    'distractors' => [
                        'Karya tersebut dinilai paling mutakhir karena mengadopsi tren bahasa populer di media sosial',
                        'Karya tersebut sangat valid karena pandangan di media sosial mewakili seluruh masyarakat dunia',
                        'Karya tersebut bebas dari kaidah ilmiah karena blog pribadi memiliki hak kekebalan akademik',
                    ],
                    'explanation' => 'Karya tulis ilmiah wajib bersandar pada fakta terverifikasi dan rujukan primer tepercaya; mengandalkan opini blog tanpa verifikasi meruntuhkan sifat ilmiah tulisan.',
                ],
            ];
            $items = $ktiPool;
        }

        // Domain 4: Teks Laporan Hasil Observasi / Wayang / Museum (BS)
        if (
            str_contains($bodyLower, 'laporan hasil observasi')
            || str_contains($titleLower, 'laporan hasil observasi')
            || str_contains($bodyLower, 'wayang purwa')
            || (str_contains($bodyLower, 'observasi') && str_contains($bodyLower, 'wayang'))
            || (str_contains($titleLower, 'bs') && str_contains($bodyLower, 'observasi'))
        ) {
            $lhoPool = [
                [
                    'bloom' => 'C1',
                    'question' => 'Sebuah siswa ingin menulis laporan hasil observasi tentang wayang golek. Berdasarkan materi, bahan utama yang digunakan untuk membuat wayang golek adalah?',
                    'correct' => 'Boneka kayu',
                    'distractors' => ['Kulit kerbau', 'Kain batik', 'Rumput suket'],
                    'explanation' => 'Wayang golek adalah jenis wayang yang menggunakan boneka kayu sebagai pemeran tokohnya.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Apa yang menjadi tujuan utama pembuatan teks laporan hasil observasi?',
                    'correct' => 'Menyajikan informasi faktual berdasarkan pengamatan langsung secara objektif',
                    'distractors' => [
                        'Menulis cerita fiksi imajinatif untuk hiburan semata',
                        'Menyampaikan opini pribadi tanpa didukung data lapangan',
                        'Mempromosikan suatu produk komersial kepada khalayak umum',
                    ],
                    'explanation' => 'Teks laporan hasil observasi bertujuan menyajikan fakta dan klasifikasi objek berdasarkan pengamatan lapangan secara objektif dan sistematis.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Mengapa wayang purwa diperkirakan memiliki umur paling tua di antara jenis wayang lainnya?',
                    'correct' => 'Karena kata "purwa" berasal dari bahasa Jawa yang berarti awal atau permulaan',
                    'distractors' => [
                        'Karena dibuat dari bahan plastik sintetis modern',
                        'Karena proses pembuatannya sederhana tanpa aturan seni',
                        'Karena hanya dipentaskan untuk upacara keagamaan modern',
                    ],
                    'explanation' => 'Wayang purwa dianggap paling tua karena penamaannya berasal dari kata "purwa" (bahasa Jawa) yang bermakna permulaan atau awal.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Apa perbedaan mendasar antara wayang wong (wayang orang) dengan wayang kulit purwa?',
                    'correct' => 'Wayang wong diperankan langsung oleh aktor manusia, sedangkan wayang kulit dimainkan dalang menggunakan bayangan kelir',
                    'distractors' => [
                        'Wayang wong menggunakan boneka kayu, wayang kulit menggunakan kain mori',
                        'Wayang wong tidak diiringi gamelan, wayang kulit diiringi musik modern',
                        'Wayang wong hanya ada di luar negeri, wayang kulit asli nusantara',
                    ],
                    'explanation' => 'Wayang wong atau wayang orang diperankan langsung oleh manusia yang mengenakan kostum wayang, berbeda dengan wayang kulit yang mengandalkan bayangan boneka kulit.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Wayang suket dan wayang motekar merupakan inovasi dalam seni pertunjukan wayang. Apa fungsi inovasi tersebut bagi pelestarian budaya?',
                    'correct' => 'Mengembangkan variasi media agar seni wayang tetap relevan dan menarik bagi generasi muda',
                    'distractors' => [
                        'Membatasi pertunjukan wayang hanya untuk kalangan bangsawan',
                        'Menghapus kaidah dan pakem wayang tradisional secara total',
                        'Menggantikan seluruh peran dalang dengan teknologi kecerdasan buatan',
                    ],
                    'explanation' => 'Pengembangan varian baru seperti wayang suket (rumput) dan motekar (animasi bayang) membuktikan kedinamisan budaya tradisional agar tetap digemari generasi baru.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Pada teks laporan hasil observasi, bagian "Deskripsi Manfaat" berfungsi untuk...',
                    'correct' => 'Menyajikan rincian kegunaan atau nilai fungsi dari objek yang diobservasi dalam kehidupan nyata',
                    'distractors' => [
                        'Menceritakan asal-usul legenda tokoh wayang secara mitologis',
                        'Menuliskan biodata lengkap pengamat dan tim observasi',
                        'Memuat daftar pustaka dan indeks buku rujukan',
                    ],
                    'explanation' => 'Bagian deskripsi manfaat menguraikan secara spesifik fungsi atau faedah objek yang diteliti bagi masyarakat dan lingkungan.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Diakui oleh UNESCO pada 7 November 2003, wayang ditetapkan sebagai "Masterpiece of Oral and Intangible Heritage of Humanity". Apa makna pengakuan dunia tersebut?',
                    'correct' => 'Pengakuan internasional bahwa wayang adalah mahakarya warisan budaya bertutur takbenda milik peradaban dunia dari Indonesia',
                    'distractors' => [
                        'Larangan bagi bangsa Indonesia untuk mementaskan wayang di luar negeri',
                        'Perintah untuk mengubah bahasa pengantar wayang menjadi bahasa internasional',
                        'Penyerahan hak milik seni wayang kepada organisasi internasional',
                    ],
                    'explanation' => 'Gelar Masterpiece dari UNESCO mengukuhkan nilai adiluhung seni wayang Indonesia sebagai warisan luhur kemanusiaan yang diakui secara global.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Sebelum menyusun draf laporan hasil observasi, langkah sistematis pertama yang wajib dilakukan oleh seorang pengamat adalah...',
                    'correct' => 'Menentukan objek yang diobservasi, menyusun jadwal observasi, dan mengumpulkan data faktual di lapangan',
                    'distractors' => [
                        'Langsung menulis kesimpulan tanpa mengamati objek',
                        'Mencari kritik negatif masyarakat terhadap objek observasi',
                        'Mempublikasikan draf laporan ke media sosial terlebih dahulu',
                    ],
                    'explanation' => 'Prosedur observasi ilmiah menuntut persiapan matang: menetapkan objek amatan, mengatur jadwal, dan mengumpulkan catatan data empiris secara cermat.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Manakah ciri kebahasaan yang paling tepat dan dominan dalam sebuah teks laporan hasil observasi ilmiah?',
                    'correct' => 'Menggunakan kalimat definisi, kata baku, istilah teknis sesuai bidang, dan kalimat deskripsi faktual',
                    'distractors' => [
                        'Menggunakan bahasa kiasan, majas hiperbola, dan ungkapan emosional subjektif',
                        'Menggunakan kalimat perintah ajakan persuasif seperti naskah iklan promosi',
                        'Menggunakan ragam bahasa percakapan sehari-hari yang santai dan tidak baku',
                    ],
                    'explanation' => 'Ciri kebahasaan laporan hasil observasi berfokus pada ketepatan makna: kalimat definisi (adalah, merupakan), verba relasional, dan istilah ilmiah yang lugas.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Dalam analisis teks laporan hasil observasi, apa yang membedakan antara "fakta hasil observasi" dengan "opini penulis"?',
                    'correct' => 'Fakta dapat diverifikasi kebenarannya berdasarkan data empiris lapangan, sedangkan opini merupakan pandangan atau penilaian pribadi',
                    'distractors' => [
                        'Fakta selalu berbentuk angka statistik, sedangkan opini selalu berbentuk kalimat panjang',
                        'Fakta tidak memerlukan bukti nyata, sedangkan opini wajib disahkan oleh pihak berwenang',
                        'Tidak ada perbedaan antara fakta dan opini dalam laporan hasil observasi',
                    ],
                    'explanation' => 'Fakta bertumpu pada kenyataan obyektif lapangan yang dapat diuji ulang, sedangkan opini mencerminkan penafsiran atau perasaan subjektif penulis.',
                ],
            ];
            $items = $lhoPool;
        }

        // Domain 5: Matematika — Eksponen, Sifat Perpangkatan, & Fungsi Eksponensial
        if (
            (str_contains($bodyLower, 'eksponen') || str_contains($titleLower, 'eksponen') || str_contains($titleLower, 'mtk') || (isset($category) && str_contains(mb_strtolower($category), 'matematika')))
            && (str_contains($bodyLower, 'pangkat') || str_contains($bodyLower, 'perkalian berulang') || str_contains($bodyLower, 'sifat-sifat eksponen') || str_contains($bodyLower, 'akar') || str_contains($bodyLower, 'fungsi eksponensial'))
        ) {
            $mtkPool = [
                [
                    'bloom' => 'C1',
                    'question' => 'Jika $a$ adalah bilangan real dan $n$ adalah bilangan bulat positif, bagaimana bilangan berpangkat (eksponen) $a^n$ didefinisikan secara matematis?',
                    'correct' => 'Perkalian berulang bilangan real $a$ sebanyak $n$ faktor ($a \times a \times \dots \times a$)',
                    'distractors' => [
                        'Penjumlahan berulang bilangan $a$ sebanyak $n$ kali ($a + a + \dots + a$)',
                        'Perkalian antara bilangan dasar $a$ dengan bilangan pangkat $n$ ($a \times n$)',
                        'Pembagian berulang bilangan dasar $a$ oleh bilangan eksponen $n$',
                    ],
                    'explanation' => 'Secara formal, eksponen $a^n$ menyatakan hasil kali bilangan real $a$ sebanyak $n$ faktor.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Berdasarkan sifat-sifat eksponen, bentuk paling sederhana dari perkalian bilangan berpangkat dengan basis yang sama $a^m \cdot a^n$ adalah...',
                    'correct' => '$a^{m+n}$',
                    'distractors' => ['$a^{m \cdot n}$', '$a^{m - n}$', '$(a \cdot a)^{m+n}$'],
                    'explanation' => 'Sifat perkalian eksponen dengan basis sama menyatakan bahwa pangkat dijumlahkan: $a^m \cdot a^n = a^{m+n}$.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Untuk bilangan real $a \neq 0$ serta $m, n$ bilangan bulat positif, bentuk sederhana dari operasi pembagian eksponen $\frac{a^m}{a^n}$ adalah...',
                    'correct' => '$a^{m-n}$',
                    'distractors' => ['$a^{\frac{m}{n}}$', '$a^{m+n}$', '$a^{-(m+n)}$'],
                    'explanation' => 'Sifat pembagian eksponen dengan basis sama menyatakan bahwa pangkat dikurangkan: $\frac{a^m}{a^n} = a^{m-n}$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Bentuk ekuivalen yang paling tepat dari pemangkatan bilangan berpangkat $(a^m)^n$ adalah...',
                    'correct' => '$a^{m \cdot n}$',
                    'distractors' => ['$a^{m+n}$', '$a^{m^n}$', '$a^{\frac{m}{n}}$'],
                    'explanation' => 'Sifat pemangkatan dari eksponen menyatakan bahwa kedua eksponen saling dikalikan: $(a^m)^n = a^{m \cdot n}$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Berapakah nilai dari bilangan berpangkat nol $a^0$ untuk setiap bilangan real $a$ dengan syarat $a \neq 0$?',
                    'correct' => '1',
                    'distractors' => ['0', '$a$', 'Tidak terdefinisi'],
                    'explanation' => 'Berdasarkan sifat pembagian $\frac{a^n}{a^n} = a^{n-n} = a^0 = 1$ untuk setiap basis $a \neq 0$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Bentuk ekuivalen dari bilangan berpangkat bulat negatif $a^{-n}$ untuk $a \neq 0$ adalah...',
                    'correct' => '$\frac{1}{a^n}$',
                    'distractors' => ['$-a^n$', '$-\frac{1}{a^n}$', '$\sqrt[n]{a}$'],
                    'explanation' => 'Pangkat bulat negatif menyatakan kebalikan pecahan (resiprokal) dari bilangan berpangkat positif: $a^{-n} = \frac{1}{a^n}$.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Bagaimanakah hubungan konseptual antara bilangan berpangkat pecahan $a^{\frac{m}{n}}$ dengan bentuk akar?',
                    'correct' => '$a^{\frac{m}{n}} = \sqrt[n]{a^m}$ dengan $n$ sebagai indeks akar dan $m$ sebagai pangkat bilangan di dalam akar',
                    'distractors' => [
                        '$a^{\frac{m}{n}} = \sqrt[m]{a^n}$ dengan $m$ sebagai indeks akar luar',
                        '$a^{\frac{m}{n}} = \frac{\sqrt{a^m}}{n}$ sebagai hasil bagi bentuk akar',
                        '$a^{\frac{m}{n}} = m \cdot \sqrt[n]{a}$ sebagai kelipatan bentuk akar',
                    ],
                    'explanation' => 'Eksponen pecahan $a^{\frac{m}{n}}$ terhubung langsung dengan bentuk akar di mana penyebut $n$ menjadi indeks akar: $\sqrt[n]{a^m}$.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Hitunglah nilai paling sederhana dari operasi bentuk aljabar eksponen $\frac{2^5 \cdot 2^3}{2^2}$!',
                    'correct' => '$2^6 = 64$',
                    'distractors' => ['$2^8 = 256$', '$2^4 = 16$', '$2^{15} = 32.768$'],
                    'explanation' => 'Terapkan sifat perkalian dan pembagian: $\frac{2^5 \cdot 2^3}{2^2} = 2^{5+3-2} = 2^6 = 64$.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Dalam pemodelan penyebaran pesan hoaks atau penularan virus dengan rasio lipat tiga ($f(x) = 3^x$), berapakah jumlah penerima pesan pada fase ke-4?',
                    'correct' => '81 orang',
                    'distractors' => ['12 orang', '27 orang', '243 orang'],
                    'explanation' => 'Pada fase ke-4, jumlah penerima dihitung dengan fungsi eksponen $f(4) = 3^4 = 3 \times 3 \times 3 \times 3 = 81$ orang.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Mengapa pola pertumbuhan eksponensial $f(x) = a^x$ (dengan $a > 1$) menghasilkan lonjakan yang jauh lebih drastis pada fase lanjut dibandingkan pola pertumbuhan linier $f(x) = ax$?',
                    'correct' => 'Karena pada pertumbuhan eksponensial nilai pada setiap tahap berlipat ganda melalui perkalian, sedangkan pertumbuhan linier hanya menambah nilai secara konstan',
                    'distractors' => [
                        'Karena fungsi eksponensial selalu memiliki nilai awal yang jauh melampaui fungsi linier',
                        'Karena fungsi linier mengalami perlambatan laju seiring bertambahnya tahapan waktu',
                        'Karena fungsi eksponensial hanya dapat dioperasikan pada basis bilangan genap',
                    ],
                    'explanation' => 'Pertumbuhan eksponensial bekerja secara multiplikatif (perkalian berulang), sehingga grafiknya melonjak semakin curam dibanding pertumbuhan aditif linier.',
                ],
            ];
            $items = $mtkPool;
        }

        // Domain 6: Matematika — Barisan dan Deret Aritmetika & Geometri (Kelas 10)
        if (
            (str_contains($bodyLower, 'barisan') && (str_contains($bodyLower, 'aritmetika') || str_contains($bodyLower, 'deret') || str_contains($bodyLower, 'geometri') || str_contains($bodyLower, 'pola bilangan')))
            || (str_contains($titleLower, 'barisan') || str_contains($titleLower, 'deret') || (isset($category) && str_contains(mb_strtolower($category), 'matematika') && (str_contains($bodyLower, 'suku ke-n') || str_contains($bodyLower, 'aritmetika'))))
        ) {
            $seqPool = [
                [
                    'bloom' => 'C1',
                    'question' => 'Pada barisan aritmetika, rumus umum untuk menentukan nilai suku ke-$n$ ($U_n$) dengan suku pertama $a$ dan beda $b$ adalah...',
                    'correct' => '$U_n = a + (n - 1)b$',
                    'distractors' => [
                        '$U_n = a + nb$',
                        '$U_n = a \cdot r^{n-1}$',
                        '$U_n = \frac{n}{2}(a + b)$',
                    ],
                    'explanation' => 'Rumus suku ke-$n$ barisan aritmetika diturunkan dari penambahan beda $b$ sebanyak $(n - 1)$ kali dari suku awal $a$, yaitu $U_n = a + (n - 1)b$.',
                ],
                [
                    'bloom' => 'C1',
                    'question' => 'Karakteristik fundamental yang mendefinisikan suatu barisan bilangan sebagai barisan aritmetika adalah...',
                    'correct' => 'Selisih antara dua suku yang berurutan selalu konstan ($b = U_n - U_{n-1}$)',
                    'distractors' => [
                        'Perbandingan rasio antara dua suku yang berurutan selalu bernilai sama',
                        'Jumlah seluruh suku selalu bernilai bilangan bulat genap',
                        'Setiap suku bernilai dua kali lipat dari suku sebelumnya',
                    ],
                    'explanation' => 'Barisan aritmetika dicirikan oleh selisih tetap (beda $b$) antarsuku yang berdekatan: $b = U_2 - U_1 = U_3 - U_2 = U_n - U_{n-1}$.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Diketahui suatu barisan bilangan: $4, 6, 8, 10, \dots$. Berapakah nilai beda ($b$) pada barisan tersebut?',
                    'correct' => '$b = 2$',
                    'distractors' => [
                        '$b = 4$',
                        '$b = 3$',
                        '$b = 1$',
                    ],
                    'explanation' => 'Beda dihitung dari selisih suku kedua dan suku pertama: $b = U_2 - U_1 = 6 - 4 = 2$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Pada barisan aritmetika $4, 6, 8, 10, \dots$, berapakah nilai suku ke-10 ($U_{10}$)?',
                    'correct' => '$U_{10} = 22$',
                    'distractors' => [
                        '$U_{10} = 20$',
                        '$U_{10} = 24$',
                        '$U_{10} = 26$',
                    ],
                    'explanation' => 'Dengan $a = 4$ dan $b = 2$, maka $U_{10} = a + (10 - 1)b = 4 + 9(2) = 4 + 18 = 22$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Suatu barisan aritmetika memiliki suku pertama $a = 5$ dan beda $b = 3$. Nilai dari suku ke-7 ($U_7$) adalah...',
                    'correct' => '$U_7 = 23$',
                    'distractors' => [
                        '$U_7 = 26$',
                        '$U_7 = 21$',
                        '$U_7 = 35$',
                    ],
                    'explanation' => 'Menggunakan rumus $U_n = a + (n - 1)b$, maka $U_7 = 5 + (7 - 1) \times 3 = 5 + 18 = 23$.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Dalam gedung pertunjukan seni, baris ke-1 memuat 20 kursi, baris ke-2 memuat 24 kursi, dan baris ke-3 memuat 28 kursi. Banyak kursi pada baris ke-5 adalah...',
                    'correct' => '36 kursi',
                    'distractors' => [
                        '32 kursi',
                        '40 kursi',
                        '44 kursi',
                    ],
                    'explanation' => 'Pola penambahan kursi tiap baris adalah beda konstan $b = 4$. Maka baris ke-5 adalah $U_5 = 20 + (5 - 1) \times 4 = 20 + 16 = 36$ kursi.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Jika pada barisan aritmetika diketahui suku pertama $U_1 = 7$ dan suku ketiga $U_3 = 15$, maka beda ($b$) barisan tersebut adalah...',
                    'correct' => '$b = 4$',
                    'distractors' => [
                        '$b = 8$',
                        '$b = 3$',
                        '$b = 2$',
                    ],
                    'explanation' => '$U_3 - U_1 = 2b \implies 15 - 7 = 8 \implies 2b = 8 \implies b = 4$.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Manakah di antara barisan bilangan berikut yang BUKAN merupakan barisan aritmetika?',
                    'correct' => '$2, 4, 8, 16, \dots$ (kelipatan rasio tetap)',
                    'distractors' => [
                        '$3, 7, 11, 15, \dots$ (selisih tetap $+4$)',
                        '$10, 7, 4, 1, \dots$ (selisih tetap $-3$)',
                        '$-5, -2, 1, 4, \dots$ (selisih tetap $+3$)',
                    ],
                    'explanation' => 'Barisan $2, 4, 8, 16, \dots$ memiliki perbandingan perkalian tetap ($\times 2$), sehingga merupakan barisan geometri, bukan barisan aritmetika.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Rudi menabung di koperasi sekolah dengan pertambahan simpanan tetap tiap bulan. Jika pada bulan ke-5 tabungan Rudi tercatat Rp70.000 ($a + 4b = 70.000$) dan selisih kenaikan tabungan antarbulan $b = \text{Rp10.000}$, maka besaran tabungan Rudi pada bulan pertama adalah...',
                    'correct' => '$\text{Rp30.000}$',
                    'distractors' => [
                        '$\text{Rp20.000}$',
                        '$\text{Rp40.000}$',
                        '$\text{Rp50.000}$',
                    ],
                    'explanation' => '$a + 4(10.000) = 70.000 \implies a + 40.000 = 70.000 \implies a = 70.000 - 40.000 = \text{Rp30.000}$.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Dalam konteks pemodelan matematika dunia nyata, mengapa pertumbuhan kapasitas gedung bertingkat atau kenaikan gaji tahunan tetap lebih tepat dimodelkan dengan barisan aritmetika daripada barisan geometri?',
                    'correct' => 'Karena peningkatannya terjadi melalui penambahan kuantitas tetap (linier), bukan kelipatan persentase eksponensial',
                    'distractors' => [
                        'Karena barisan geometri hanya berlaku untuk besaran uang berskala internasional',
                        'Karena rumus barisan aritmetika menghasilkan perhitungan yang selalu lebih besar dari barisan geometri',
                        'Karena barisan aritmetika tidak membutuhkan data suku pertama untuk menentukan suku berikutnya',
                    ],
                    'explanation' => 'Barisan aritmetika memodelkan pertumbuhan linier dengan selisih tetap ($b$), sangat sesuai untuk alokasi fisik tetap dan kenaikan nominal berkala.',
                ],
            ];

            $geoPool = [
                [
                    'bloom' => 'C1',
                    'question' => 'Karakteristik fundamental yang membedakan barisan geometri dari barisan lainnya adalah...',
                    'correct' => 'Perbandingan (rasio) antara dua suku yang berurutan selalu bernilai tetap ($r = \frac{U_n}{U_{n-1}}$)',
                    'distractors' => [
                        'Selisih antara dua suku yang berurutan selalu bernilai sama ($b = U_n - U_{n-1}$)',
                        'Setiap suku diperoleh dari penjumlahan suku sebelumnya dengan bilangan kuadrat',
                        'Jumlah seluruh suku selalu bernilai bilangan bulat genap',
                    ],
                    'explanation' => 'Barisan geometri dicirikan oleh nilai perbandingan atau rasio $r$ yang konstan antardua suku yang berurutan: $r = \frac{U_2}{U_1} = \frac{U_3}{U_2} = \frac{U_n}{U_{n-1}}$.',
                ],
                [
                    'bloom' => 'C1',
                    'question' => 'Pada barisan geometri dengan suku pertama $a$ dan rasio $r$, rumus umum untuk menentukan suku ke-$n$ ($U_n$) adalah...',
                    'correct' => '$U_n = a \cdot r^{n-1}$',
                    'distractors' => [
                        '$U_n = a + (n - 1)r$',
                        '$U_n = a \cdot r^n$',
                        '$U_n = \frac{a}{r^{n-1}}$',
                    ],
                    'explanation' => 'Suku ke-$n$ barisan geometri diturunkan dari pengalian rasio $r$ sebanyak $(n - 1)$ kali terhadap suku pertama $a$, yaitu $U_n = a \cdot r^{n-1}$.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Diketahui suatu barisan bilangan: $2, 6, 18, 54, \dots$. Berapakah nilai rasio ($r$) pada barisan tersebut?',
                    'correct' => '$r = 3$',
                    'distractors' => [
                        '$r = 4$',
                        '$r = 2$',
                        '$r = 6$',
                    ],
                    'explanation' => 'Rasio dihitung dengan membagi suku kedua dengan suku pertama: $r = \frac{U_2}{U_1} = \frac{6}{2} = 3$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Suatu barisan geometri memiliki suku pertama $a = 4$ dan rasio $r = 2$. Nilai suku ke-5 ($U_5$) dari barisan tersebut adalah...',
                    'correct' => '$U_5 = 64$',
                    'distractors' => [
                        '$U_5 = 32$',
                        '$U_5 = 128$',
                        '$U_5 = 40$',
                    ],
                    'explanation' => 'Dengan rumus $U_n = a \cdot r^{n-1}$, maka $U_5 = 4 \cdot 2^{5-1} = 4 \cdot 2^4 = 4 \cdot 16 = 64$.',
                ],
                [
                    'bloom' => 'C3',
                    'question' => 'Dalam contoh pembelahan bakteri pada materi, satu sel membelah menjadi 3 setiap 2 jam. Jika mula-mula terdapat 2 sel bakteri, berapakah banyak bakteri setelah 6 jam (terjadi 3 kali pembelahan)?',
                    'correct' => '54 bakteri',
                    'distractors' => [
                        '18 bakteri',
                        '36 bakteri',
                        '162 bakteri',
                    ],
                    'explanation' => 'Mula-mula $a = 2$, rasio pembelahan $r = 3$. Setelah 6 jam (3 fase pembelahan), jumlah bakteri adalah suku ke-4: $U_4 = a \cdot r^3 = 2 \cdot 3^3 = 2 \cdot 27 = 54$ bakteri.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Suku pertama dari suatu barisan geometri adalah $4$ dan suku ke-4 adalah $108$. Nilai rasio ($r$) dari barisan tersebut adalah...',
                    'correct' => '$r = 3$',
                    'distractors' => [
                        '$r = 2$',
                        '$r = 4$',
                        '$r = 9$',
                    ],
                    'explanation' => 'Menggunakan rumus $U_4 = a \cdot r^3 \implies 108 = 4 \cdot r^3 \implies r^3 = 27 \implies r = 3$.',
                ],
                [
                    'bloom' => 'C4',
                    'question' => 'Seutas tali dipotong menjadi 5 bagian membentuk barisan geometri. Potongan terpendek $a = 16\text{ cm}$ dan potongan terpanjang $U_5 = 81\text{ cm}$. Berapakah panjang potongan tali ke-3 ($U_3$)?',
                    'correct' => '$36\text{ cm}$',
                    'distractors' => [
                        '$24\text{ cm}$',
                        '$48\text{ cm}$',
                        '$54\text{ cm}$',
                    ],
                    'explanation' => '$16 \cdot r^4 = 81 \implies r^4 = \frac{81}{16} = (\frac{3}{2})^4 \implies r = \frac{3}{2}$. Maka $U_3 = 16 \cdot (\frac{3}{2})^2 = 16 \cdot \frac{9}{4} = 36\text{ cm}$.',
                ],
                [
                    'bloom' => 'C2',
                    'question' => 'Pada ilustrasi pembagian kue di materi, setiap anak mengambil setengah dari kue yang diterimanya. Pola bagian kue yang berurutan membentuk barisan geometri dengan nilai rasio...',
                    'correct' => '$r = \frac{1}{2}$',
                    'distractors' => [
                        '$r = 2$',
                        '$r = -\frac{1}{2}$',
                        '$r = \frac{1}{4}$',
                    ],
                    'explanation' => 'Setiap anak berikutnya menerima setengah dari bagian anak sebelumnya, sehingga perbandingannya bernilai tetap $r = \frac{1}{2}$ (barisan geometri turun).',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Perbedaan paling mendasar dalam pola pertumbuhan nilai antara barisan aritmetika dengan barisan geometri terletak pada...',
                    'correct' => 'Barisan aritmetika bertambah secara aditif (selisih tetap $+b$), sedangkan barisan geometri bertambah secara multiplikatif (faktor pengali tetap $\times r$)',
                    'distractors' => [
                        'Barisan aritmetika selalu menghasilkan angka lebih besar daripada barisan geometri',
                        'Barisan geometri tidak memerlukan suku pertama untuk menentukan suku-suku berikutnya',
                        'Barisan aritmetika hanya berlaku pada bilangan genap sedangkan geometri pada bilangan ganjil',
                    ],
                    'explanation' => 'Pertumbuhan aritmetika bersifat linier dengan penambahan beda konstan, sedangkan pertumbuhan geometri bersifat eksponensial dengan perkalian rasio konstan.',
                ],
                [
                    'bloom' => 'C5',
                    'question' => 'Apakah perbedaan konseptual antara istilah "barisan" dengan istilah "deret" dalam matematika?',
                    'correct' => 'Barisan adalah susunan urutan bilangan berpola ($U_1, U_2, \dots$), sedangkan deret adalah jumlah kumulatif suku-suku barisan tersebut ($S_n = U_1 + U_2 + \dots + U_n$)',
                    'distractors' => [
                        'Barisan hanya memiliki satu suku, sedangkan deret memiliki banyak suku berulang',
                        'Barisan menggunakan rasio sedangkan deret selalu menggunakan beda',
                        'Barisan bernilai positif sedangkan deret selalu menghasilkan bilangan negatif',
                    ],
                    'explanation' => 'Barisan merupakan urutan daftar nilai teratur, sedangkan deret merepresentasikan operasi penjumlahan total dari suku-suku barisan tersebut.',
                ],
            ];

            $prefersGeometry = (str_contains($bodyLower, 'geometri') || str_contains($titleLower, 'geometri') || str_contains($bodyLower, 'rasio'))
                && ! (str_contains($bodyLower, 'aritmetika') && ! str_contains($bodyLower, 'rasio'));

            $items = $prefersGeometry
                ? array_merge($geoPool, $seqPool)
                : array_merge($seqPool, $geoPool);
        }

        if (empty($items)) {
            return null;
        }

        $keys = ['A', 'B', 'C', 'D'];
        $formatted = [];
        foreach (array_slice($items, 0, $count) as $idx => $item) {
            $seq = $idx + 1;
            $correctKey = match ($seq % 4) {
                1 => 'A',
                2 => 'B',
                3 => 'C',
                default => 'D',
            };

            $options = [];
            $dIdx = 0;
            foreach ($keys as $k) {
                $options[] = [
                    'key' => $k,
                    'text' => $k === $correctKey ? $item['correct'] : ($item['distractors'][$dIdx++] ?? 'Pernyataan tidak sesuai dengan prinsip materi'),
                ];
            }

            $formatted[] = [
                'sequence' => $seq,
                'bloom_level' => $item['bloom'] ?? 'C2',
                'question' => $item['question'],
                'options' => $options,
                'correct_answer' => $correctKey,
                'explanation' => "Kunci Jawaban ({$correctKey}): {$item['correct']}. Pembahasan: {$item['explanation']}",
            ];
        }

        return $formatted;
    }

    /**
     * Menghasilkan Soal Pilihan Ganda berfokus substantif & analitis pada isi materi bacaan nyata
     * dengan jaminan 100% bebas soal duplikat/sama.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     */
    public function generateHeuristicQuizQuestions(string $body, string $title, ?string $category = null, int $count = 10): array
    {
        $count = min(50, max(5, $count));

        // 1. Prioritaskan pemetaan domain pengetahuan terstruktur (bebas templat kaku, opsi terdistribusi unik, dan analitis)
        $domainQuestions = $this->generateStructuredKnowledgeQuiz($body, $title, $category, $count);
        if (! empty($domainQuestions) && count($domainQuestions) >= $count) {
            return array_slice($domainQuestions, 0, $count);
        }

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
        if (! empty($domainQuestions)) {
            foreach ($domainQuestions as $dq) {
                $qKey = mb_strtolower(trim($dq['question']));
                $usedQuestions[$qKey] = true;
                $selectedItems[] = [
                    'bloom_level' => $dq['bloom_level'] ?? 'C2',
                    'question' => $dq['question'],
                    'correct' => '',
                    'distractors' => [],
                    'explanation' => $dq['explanation'] ?? '',
                    'preformatted' => $dq,
                ];
            }
        }
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
        $globalUsedDistractors = [];
        foreach (array_slice($selectedItems, 0, $count) as $index => $built) {
            $seq = $index + 1;
            if (! empty($built['preformatted'])) {
                $pf = $built['preformatted'];
                $pf['sequence'] = $seq;
                $questions[] = $pf;
                continue;
            }

            $options = $this->buildUniqueQuizOptions(
                $built['correct'],
                $built['distractors'],
                $seq,
                $termPool,
                $predicatePool,
                $built['question'],
                $globalUsedDistractors
            );

            $explanationText = $built['explanation'];
            if (! str_contains($explanationText, 'Kunci Jawaban')) {
                $explanationText = "Kunci Jawaban ({$options['correct_key']}): {$built['correct']}. {$explanationText}";
            }

            $questions[] = [
                'sequence' => $seq,
                'bloom_level' => $built['bloom_level'] ?? 'C2',
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
        $lineBoilerplate = '/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you|halaman|page\s*\d+|lembar kerja|latar belakang|ringkasan eksekutif|rumusan masalah|proposal perancangan|menjelaskan pengertian|mengidentifikasi\s+(?:kedudukan|perbedaan)|menyusun\s+(?:strategi|ringkasan|rangkuman)|menunjukkan\s+(?:sikap|kemampuan)|matematika\s+untuk\s+sma|bahasa\s+indonesia\s+untuk\s+sma|buku\s+siswa\s+kelas|bab\s+\d+|world\s+our\s+world|data\s+source|cc\s+by)/iu';
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

            $tNoZero = str_replace("\0", '', $line);
            if (preg_match('#^/(?:IQIR|IFYH|6ITY|1EXI|4IRY)#i', $tNoZero)
                || preg_match('/^(?:6ITY|1EXI|4IRY|6MWI|8IOR)/i', $tNoZero)
                || preg_match('/^(?:[\/\p{L}\p{N}%&_()\-]\s+){5,}/u', $tNoZero)
                || preg_match('/^[-–—\s]*\d+\s*&\s*\d+/u', $tNoZero)) {
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
            // Lewati baris nama pengarang / judul sampul (mis. "Bahasa Indonesia Ringkasan, Rangkuman & Ikhtisar Rizal D. Syifa")
            if (preg_match('/^(?:Bahasa Indonesia\s+)?(?:Ringkasan,\s*Rangkuman(?:\s*&?\s*Ikhtisar)?)\s+[A-Z][\p{L}\-]*\s+[A-Z]\.?\s*(?:Syifa)?$/iu', $line)) {
                continue;
            }
            if (preg_match('/\b(?:Rizal\s+D\.?|Syifa)\b/iu', $line) && ! preg_match('/\b(?:adalah|merupakan|yaitu|didefinisikan|konsep|memadukan)\b/iu', $line)) {
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
                $s = preg_replace('/^[\s"“”\']+|[\s"“”\']+$/u', '', $s) ?? trim($s);

                if ($s === '' || mb_strlen($s) < 28 || mb_strlen($s) > 540) {
                    continue;
                }
                if (preg_match($lineBoilerplate, $s) || preg_match($biblioPattern, $s) || preg_match($publisherPattern, $s)) {
                    continue;
                }
                if (preg_match('/\b(?:Rizal\s+D\.?|Syifa)\b/iu', $s) && ! preg_match('/\b(?:adalah|merupakan|yaitu|menurut|menjelaskan)\b/iu', $s)) {
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
                // Tolak kalimat tujuan pembelajaran / capaian kurikulum yang bukan materi ajar
                if (preg_match('/^(?:Setelah\s+mempelajari\s+bab|Kamu\s+diharapkan|Diharapkan\s+kamu|Pada\s+akhir\s+fase|Peserta\s+didik\s+mampu)\b/iu', $s)) {
                    continue;
                }
                // Tolak instruksi tugas / eksplorasi / diskusi kelompok
                if (preg_match('/^(?:Ayo,?\s+Bereksplorasi|Ayo,?\s+Berdiskusi|Ayo,?\s+Berpikir|Ayo,?\s+Berkolaborasi|Jawablah\s+pertanyaan|Diskusikan\s+dengan|Kerjakan\s+latihan|Perhatikan\s+Tabel|Tabel\s+\d+\.\d+)\b/iu', $s)) {
                    continue;
                }
                // Tolak bagan peta materi atau diagram alur teks
                if (preg_match('/\b(?:Peta\s+Materi|Bagan\s+Alur|Diagram\s+Konsep)\b/iu', $s)) {
                    continue;
                }
                // Tolak kalimat terpotong di kata penghubung/preposisi (dangling sentence)
                if (preg_match('/\b(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|adalah|merupakan|yaitu|yakni|bahwa)$/iu', $s)) {
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
            if (! mb_check_encoding($term, 'UTF-8')) {
                return;
            }
            $clean = preg_replace('/^[\s.,;:!?"\'()\[\]{}±~–—“”‘’…]+|[\s.,;:!?"\'()\[\]{}±~–—“”‘’…]+$/u', '', $term) ?? trim($term);
            $clean = preg_replace('/\s+(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|ini|itu|tersebut)$/iu', '', $clean) ?? $clean;
            $clean = preg_replace('/^(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|ini|itu|tersebut)\s+/iu', '', $clean) ?? $clean;
            $clean = trim($clean);
            if (mb_strlen($clean) < 4 || mb_strlen($clean) > 55) {
                return;
            }
            // Istilah kunci kuis wajib mengandung kata huruf yang valid
            if (! preg_match('/\p{L}{3,}/u', $clean)) {
                return;
            }
            // TOLAK karakter simbol atau rentang angka (misal ±, %, ~, /, \, [, ])
            if (preg_match('/[±~%\/\\_\[\]{}]/u', $clean)) {
                return;
            }
            // TOLAK angka yang menempel dengan kata atau awalan penomoran (misal 150-250kata, 3.Bagian, 1.Objektif)
            if (preg_match('/\d+[\-–—\.]*\d*\s*kata\b/iu', $clean) || preg_match('/^\d+[\.\)]\s*/u', $clean) || preg_match('/\b\d+[a-zA-Z]/u', $clean)) {
                return;
            }
            // TOLAK penamaan bab, label daftar isi, atau heading struktural
            if (preg_match('/^(?:Bab\s+[IVXLCDM\d]+|Bagian\s+(?:Awal|Isi|Akhir)|Daftar\s+Pustaka|Lampiran|Struktur\s+Umum)\b/iu', $clean)) {
                return;
            }
            // TOLAK kata yang menempel tanpa spasi (panjang >= 14 huruf tanpa tanda hubung dan memuat sub-kata umum)
            if (preg_match('/^[a-zA-Z]{14,}$/u', $clean) && preg_match('/(?:dan|atau|serta|pada|untuk|dalam|yang|dari|dengan|kesimpulan|saran|bagian|penutup)/iu', $clean)) {
                return;
            }
            // TOLAK tanda kurung yang tidak seimbang
            if (substr_count($clean, '(') !== substr_count($clean, ')')) {
                return;
            }
            if (preg_match('/^(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|adalah|merupakan|yaitu|ini|itu|tersebut|sehingga|karena|bahwa|akan|telah|dapat|harus|lebih|sangat|secara|antara|melalui|setelah)$/iu', $clean)) {
                return;
            }
            if (preg_match('/\b(?:adalah|merupakan|yaitu|yakni|sebagai|diletakkan|diposisikan|pun|dan|atau|yang|di|ke|dari|untuk|pada|dengan|dalam|karena|sehingga|contoh|penggunaan|catatan|aspek|butir|tentang|terkait|prinsip|aturan|nomor)\b/iu', $clean)) {
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
            // Abaikan kalimat ajakan, instruksi buku, atau meta-teks yang tidak substantif
            if (preg_match('/\b(?:kamu pasti|apakah kamu|tugasmu|pada pelajaran ini|pelajari lebih dulu|sumber foto|perhatikan gambar|halaman \d+|agar kamu lebih mudah|untuk membekali kemampuan)\b/iu', $s)) {
                continue;
            }
            if (preg_match('/^.{8,70}?\s+(?:adalah|merupakan|yaitu|yakni|dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|berfungsi\s+sebagai|berperan\s+sebagai|mendeteksi|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan|memiliki\s+makna|artinya)\s+(.+)$/iu', $s, $m)) {
                $clause = ucfirst(rtrim(trim($m[1]), '.'));
                if (mb_strlen($clause) >= 15 && mb_strlen($clause) <= 150) {
                    $pool[] = $clause;
                }
            } elseif (preg_match('/^[A-Z][a-z].{15,120}$/u', trim($s)) && ! str_contains($s, '?') && ! str_contains($s, '!') && str_word_count($s) >= 4 && str_word_count($s) <= 20) {
                $pool[] = rtrim(trim($s), '.');
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
        $isJunkOptionOrSubject = function (string $text): bool {
            $t = mb_strtolower(trim($text));
            if ($t === '') return true;
            if (in_array($t, ['berikut', 'sebagai berikut', 'berikut ini', 'di bawah ini', 'di atas', 'seperti berikut', 'hal ini', 'hal tersebut', 'antara lain', 'adalah', 'merupakan', 'yaitu', 'yakni'], true)) {
                return true;
            }
            if (str_starts_with($t, 'berikut:') || str_starts_with($t, 'sebagai berikut:')) {
                return true;
            }
            if (preg_match('/^(?:matematika\s+untuk|bahasa\s+indonesia\s+untuk|sma\/ma\/smk|buku\s+siswa|bab\s+\d+|kelas\s+[x\d]+|world\s+our\s+world|data\s+source|cc\s+by)/iu', $t)) {
                return true;
            }
            if (preg_match('/^(?:setelah\s+mempelajari|diharapkan\s+kamu|kamu\s+diharapkan|ayo,?\s+bereksplorasi|ayo,?\s+berkolaborasi|ayo,?\s+berpikir|jawablah\s+pertanyaan|peta\s+materi|kementerian\s+pendidikan|tabel\s+\d+|gambar\s+\d+)/iu', $t)) {
                return true;
            }
            if (preg_match('/\b(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|adalah|merupakan|yaitu|yakni|bahwa)$/iu', $t)) {
                return true;
            }
            if (mb_strlen($t) < 8 && str_word_count($t) < 2) {
                return true;
            }
            return false;
        };

        $condensedOptionPool = array_values(array_filter(
            array_map(fn ($p) => $this->condenseClauseToOption($p), $predicatePool),
            fn ($p) => $p !== '' && ! $isJunkOptionOrSubject($p)
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
            $rawSubject = preg_replace('/^[\s"“”‘’\']+|[\s"“”‘’\']+$/u', '', $m[1]) ?? trim($m[1]);
            $subject = preg_replace('/^(?:salah satu|sebagai contoh|contohnya|pada dasarnya|dalam hal ini|secara umum)\s+/iu', '', $rawSubject) ?? $rawSubject;
            $definition = $this->condenseClauseToOption(ucfirst(rtrim(trim($m[3]), '.')));
            if (
                preg_match('/\b(?:karena|sebab|sehingga|walaupun|meskipun)\b/iu', $subject)
                || preg_match('/^(?:jenis yang|hal yang|bagian yang)\b/iu', $subject)
                || $isJunkOptionOrSubject($subject)
                || $isJunkOptionOrSubject($definition)
            ) {
                // Lewati subjek yang merupakan klausa relatif, tidak substantif, atau definisi rusak/kata sambung
            } else {
                $verb = mb_strtolower(trim($m[2]));

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
        }

        // Angle 3 (C3 Menerapkan): Tujuan & Penerapan Langkah
        if (preg_match('/^(.{15,150}?)\s+(?:dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|untuk|guna|demi|agar)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectAction = $this->condenseSentenceToTopic(rtrim(trim($m[1]), ', '), 5);
            $goal = $this->condenseClauseToOption(ucfirst(rtrim(trim($m[2]), '.')));
            if (! $isJunkOptionOrSubject($subjectAction) && ! $isJunkOptionOrSubject($goal)) {
                $candidates[] = [
                    'bloom_level' => 'C3',
                    'question' => "Terapkan pemahaman materi: untuk sasaran apa \"{$subjectAction}\" dijalankan?",
                    'correct' => $goal,
                    'distractors' => array_map(fn ($g) => $this->condenseClauseToOption($g), $this->buildGoalDistractors($goal, $allSentences, $predicatePool)),
                    'explanation' => "Pembahasan: Tindakan \"{$subjectAction}\" ditujukan untuk " . lcfirst($goal) . '.',
                ];
            }
        }

        // Angle 3b (C4 Menganalisis): Temuan / Mekanisme / Hubungan Kausal
        if (preg_match('/^(.{15,140}?)\s+(mendeteksi\s+(?:adanya\s+)?|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan|disebabkan\s+oleh|karena)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectPart = $this->condenseSentenceToTopic(rtrim(trim($m[1]), ', '), 5);
            $connector = mb_strtolower(trim($m[2]));
            $detail = $this->condenseClauseToOption(ucfirst(rtrim(trim($m[3]), '.')));

            if (! $isJunkOptionOrSubject($subjectPart) && ! $isJunkOptionOrSubject($detail)) {
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
        }

        // Angle 4 (C5 Mengevaluasi & C3 Menerapkan): Evaluasi Pernyataan Berdasarkan Istilah Kunci (pencarian kata utuh)
        $matchedTerms = [];
        foreach ($termPool as $term) {
            if ($this->containsWholeWordTerm($sentence, $term)) {
                $matchedTerms[] = $term;
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
            [$bloom, $promptByConcept] = match ($idxTerm % 4) {
                0 => ['C5', "Evaluasi penerapan prinsip \"{$concept}\": tindakan atau simpulan manakah yang paling selaras dengan kaidah materi?"],
                1 => ['C3', "Terapkan pemahaman kaidah: dalam situasi nyata, bagaimana perlakuan yang tepat terhadap \"{$concept}\"?"],
                2 => ['C4', "Analisis kritis materi: konsekuensi logis apa yang timbul apabila ketentuan mengenai \"{$concept}\" diabaikan?"],
                default => ['C4', "Berdasarkan analisis materi, keterkaitan peran atau fungsi manakah yang paling tepat menjelaskan \"{$concept}\"?"],
            };

            $candidates[] = [
                'bloom_level' => $bloom,
                'question' => $promptByConcept,
                'correct' => $condensedCoreOption,
                'distractors' => ! empty($conceptDistractors) ? $conceptDistractors : $otherOptionPool,
                'explanation' => "Pembahasan: Evaluasi materi mengenai gagasan \"{$concept}\" menegaskan bahwa: {$condensedCoreOption}. Opsi lain tidak mencerminkan prinsip utama bacaan.",
            ];
        }

        // Angle 5 (C4 Menganalisis & C5 Mengevaluasi): Peranan Struktural & Evaluasi Prinsip (tanpa pemotongan acak di tengah kalimat)
        if (preg_match('/^(.{4,55}?)\s+(?:berfungsi|berperan|bertujuan|memadukan|menuntut|mengatur|menjamin|melindungi|mencakup|menjadi)\s+(.{12,})$/iu', $sentence, $predMatch)) {
            $entitySubject = preg_replace('/^[\s"“”‘’\']+|[\s"“”‘’\']+$/u', '', $predMatch[1]) ?? trim($predMatch[1]);
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
            $qEvaluasi = "Bagaimanakah penilaian atau simpulan yang paling tepat mengenai gagasan \"{$cleanTopic}\"?";
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
     * @param array<string, bool> $globalUsedDistractors
     * @return array{options: array<int, array{key: string, text: string}>, correct_key: string}
     */
    private function buildUniqueQuizOptions(
        string $correct,
        array $candidateDistractors,
        int $sequence,
        array $termPool,
        array $predicatePool,
        string $question = '',
        array &$globalUsedDistractors = []
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
                'Membatasi cakupan penerapan hanya pada tataran teori semata',
                'Mengurangi efektivitas sistem dengan mengabaikan fungsi pokok',
                'Mengubah susunan struktur tanpa landasan aturan yang baku',
                'Menyeragamkan seluruh aspek tanpa penyesuaian kebutuhan',
            ];
            $condensedPredicates = array_values(array_filter(
                array_map(fn ($p) => $this->condenseClauseToOption((string) $p), $predicatePool),
                fn ($p) => $p !== ''
            ));
            $mergedPool = array_merge($candidateDistractors, $condensedPredicates, $termPool, $neutralFallbacks);
        }

        // Putaran 1: Prioritaskan kandidat yang belum pernah digunakan di soal manapun (mencegah pengulangan opsi antar-soal)
        foreach ($mergedPool as $cand) {
            $c = trim((string) $cand);
            if (! $this->isPlausibleQuizOption($c, $correctClean, $question)) {
                continue;
            }
            $lower = mb_strtolower($c);
            if (isset($usedLower[$lower]) || isset($globalUsedDistractors[$lower])) {
                continue;
            }
            $usedLower[$lower] = true;
            $globalUsedDistractors[$lower] = true;
            $distractors[] = $c;
            if (count($distractors) >= 3) {
                break;
            }
        }

        // Putaran 2: Jika masih kurang dari 3 opsi, pilih kandidat yang belum dipakai di soal ini
        if (count($distractors) < 3) {
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
                $globalUsedDistractors[$lower] = true;
                $distractors[] = $c;
                if (count($distractors) >= 3) {
                    break;
                }
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
                    $globalUsedDistractors[$lower] = true;
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

        if (! mb_check_encoding($cleanNeedle, 'UTF-8') || ! mb_check_encoding($haystack, 'UTF-8')) {
            return false;
        }

        try {
            return @preg_match('/(?<![\p{L}\p{N}])' . preg_quote($cleanNeedle, '/') . '(?![\p{L}\p{N}])/iu', $haystack) === 1;
        } catch (Throwable) {
            return false;
        }
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

        if (! mb_check_encoding($haystack, 'UTF-8')) {
            return false;
        }

        foreach ($cleanWords as $w) {
            if (! mb_check_encoding($w, 'UTF-8')) {
                return false;
            }
        }

        $pattern = implode('\s+', array_map(fn (string $w) => preg_quote($w, '/'), $cleanWords));

        try {
            return @preg_match('/(?<![\p{L}\p{N}])' . $pattern . '(?![\p{L}\p{N}])/iu', $haystack) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Memadatkan kalimat sumber menjadi topik ringkas untuk batang soal (tanpa menyalin paragraf utuh).
     */
    private function condenseSentenceToTopic(string $sentence, int $maxWords = 6): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', str_replace(['→', '->'], ' ', strip_tags($sentence))) ?? '');
        $clean = trim(preg_replace('/^(?:Kedudukan|Fungsi(?:\s+utama)?|Prinsip|Landasan|Syarat|Tujuan(?:\s+utama)?|Contoh(?:\s+penggunaan)?|Catatan|Aspek|Butir)\s*:\s*/iu', '', $clean) ?? $clean);
        $clean = preg_replace('/^(?:oleh karena itu|karena itu|dengan demikian|selain itu|namun|akan tetapi),?\s+/iu', '', $clean) ?? $clean;

        // Jika terdapat kopula (adalah/merupakan/memiliki) dengan subjek ringkas di awal, gunakan subjeknya saja agar tidak terpotong di tengah predikat
        if (preg_match('/^([^,.]{6,55}?)\s+(?:adalah|merupakan|yaitu|memiliki|menuntut|bertujuan|berfungsi)\b/iu', $clean, $subMatch)) {
            $clean = trim($subMatch[1]);
        }

        $cleanWords = preg_replace('/^[\s.,;:!?"\'“”‘’]+|[\s.,;:!?"\'“”‘’]+$/u', '', $clean) ?? trim($clean);
        $words = preg_split('/\s+/u', $cleanWords, -1, PREG_SPLIT_NO_EMPTY) ?: [];

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
        $clean = trim(preg_replace('/^(?:Kedudukan|Fungsi(?:\s+utama)?|Prinsip|Landasan|Ciri(?:\s+utama)?|Contoh(?:\s+penggunaan)?|Catatan|Aspek|Butir)\s*:\s*/iu', '', $clean) ?? $clean);
        $clean = trim(preg_replace('/^(?:Bab\s+[IVXLCDM\d]+[:.]?\s*|[\d]+[.)]\s*|Bagian\s+(?:Awal|Isi|Akhir)[:.]?\s*)+/iu', '', $clean) ?? $clean);
        $clean = preg_replace('/^[&+\-*\/,;:|~_–—\s\)]+|[&+\-*\/,;:|~_–—\s\(]+$/u', '', $clean) ?? $clean;
        $clean = preg_replace('/^(?:dan|atau|serta|yaitu|yakni|adalah|merupakan|bahwa|sehingga|karena|dengan demikian)\s+/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/^[\s"\'“”‘’()]+|[\s"\'“”‘’().]+$/u', '', $clean) ?? trim($clean);

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
            $candidate = rtrim(trim($clauses[0]), '.,;:');
        } else {
            $cut = mb_substr($clean, 0, $maxChars - 1);
            $lastSpace = mb_strrpos($cut, ' ');
            $candidate = rtrim(($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut), '.,;: ');
        }

        // Pastikan opsi jawaban tidak pernah terpotong menggantung di kata sambung atau preposisi
        $candidate = preg_replace('/\s+(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|adalah|merupakan|yaitu|yakni|bahwa)$/iu', '', $candidate) ?? $candidate;

        return trim($candidate);
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

        // Jika terdapat frasa kutipan tanda petik dalam pertanyaan yang terkandung di dalam jawaban
        if (preg_match('/"([^"]{5,})"|“([^”]{5,})”|`([^`]{5,})`/u', $question, $quoteMatch)) {
            $quoted = mb_strtolower(trim($quoteMatch[1] ?: ($quoteMatch[2] ?: ($quoteMatch[3] ?? ''))));
            if ($quoted !== '' && str_contains($aLower, $quoted)) {
                return true;
            }
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
            if (($hits / count($aWords)) >= 0.5) {
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

        // 1. Bersihkan markdown fences jika ada
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $cleanJson = trim((string) preg_replace('/\s*```$/', '', (string) $cleanJson));

        // 2. Cari kurung kurawal pertama (abaikan monolog/intro sebelum JSON)
        $startPos = mb_strpos($cleanJson, '{');
        if ($startPos !== false) {
            $candidate = mb_substr($cleanJson, $startPos);

            // Coba decode jika ada penutup lengkap
            if (preg_match('/\{[\s\S]*\}/u', $candidate, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }

            // 3. Jika terpotong di akhir (token limit), pulihkan secara cerdas dari kurung kurawal terakhir
            $lastBrace = mb_strrpos($candidate, '}');
            $safetyLoop = 0;
            while ($lastBrace !== false && $lastBrace > 15 && $safetyLoop < 20) {
                $safetyLoop++;
                $sub = mb_substr($candidate, 0, $lastBrace + 1);

                $attempt1 = json_decode($sub . ']}', true);
                if (is_array($attempt1) && (! empty($attempt1['quiz_questions']) || ! empty($attempt1['questions']))) {
                    return $attempt1;
                }

                $attempt2 = json_decode($sub . '}', true);
                if (is_array($attempt2) && (! empty($attempt2['excerpt']) || ! empty($attempt2['questions']))) {
                    return $attempt2;
                }

                $lastBrace = mb_strrpos(mb_substr($candidate, 0, $lastBrace), '}');
            }
        }

        $parsed = json_decode($cleanJson, true);

        return is_array($parsed) ? $parsed : null;
    }

    private function decodeJsonArray(string $raw): ?array
    {
        $trimmed = trim($raw);

        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $cleanJson = trim((string) preg_replace('/\s*```$/', '', (string) $cleanJson));

        $startPos = mb_strpos($cleanJson, '[');
        if ($startPos !== false) {
            $candidate = mb_substr($cleanJson, $startPos);

            if (preg_match('/\[[\s\S]*\]/u', $candidate, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }

            $lastBrace = mb_strrpos($candidate, '}');
            $safetyLoop = 0;
            while ($lastBrace !== false && $lastBrace > 15 && $safetyLoop < 20) {
                $safetyLoop++;
                $sub = mb_substr($candidate, 0, $lastBrace + 1);
                $attempt = json_decode($sub . ']', true);
                if (is_array($attempt) && ! empty($attempt)) {
                    return $attempt;
                }
                $lastBrace = mb_strrpos(mb_substr($candidate, 0, $lastBrace), '}');
            }
        }

        $parsed = json_decode($cleanJson, true);

        return is_array($parsed) ? $parsed : null;
    }
}
