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
     * Membangun konteks representatif seluruh bagian materi (awal, tengah, hingga akhir)
     * agar tidak pernah hanya menganalisis bagian awal dokumen.
     */
    public function buildFullCoverageContext(string $body, int $maxChars = 15000): string
    {
        $clean = trim($body);
        $len = mb_strlen($clean);
        if ($len <= $maxChars) {
            return $clean;
        }

        $segments = 5;
        $chunkSize = intdiv($maxChars - 120, $segments);
        $parts = [];
        for ($i = 0; $i < $segments; $i++) {
            if ($i === 0) {
                $start = 0;
            } elseif ($i === $segments - 1) {
                $start = max(0, $len - $chunkSize);
            } else {
                $start = max(0, intdiv($i * ($len - $chunkSize), $segments - 1));
            }
            $parts[] = "[BAGIAN " . ($i + 1) . "/{$segments}]\n" . mb_substr($clean, $start, $chunkSize);
        }

        return implode("\n\n...\n\n", $parts);
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
        $sampleText = $this->buildFullCoverageContext($formattedBody, 14000);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Kurator Literasi & Asesor Berpikir Kritis SocratesRead AI.
Tugasmu adalah menganalisis KESELURUHAN isi materi bacaan guru (dari bagian awal, inti konsep, hingga akhir) dan menghasilkan JSON yang akurat, mendalam, serta mengasah logika kritis siswa.

Format output WAJIB HANYA berupa JSON valid tanpa teks lain:
{
  "cleaned_body": "teks materi lengkap jika dirapikan (jangan dipotong/diringkas), atau kosongkan string jika teks sudah rapi",
  "excerpt": "Ringkasan intisari materi 2-3 kalimat yang padat dan jelas...",
  "questions": [
    {
      "s": "Memahami gagasan",
      "d": "easy",
      "q": "Pertanyaan esai langsung dan kritis (TANPA awalan klise 'Berdasarkan teks materi...')",
      "c": "Konteks singkat atau fokus pemikiran yang membantu siswa mengarahkan jawaban",
      "h": "Kunci jawaban model berparafrase alami (bahasa Anda sendiri yang rapi, objektif, dan bebas sapaan 'anda/silahkan/gambar di bawah') yang akurat menjawab pertanyaan sesuai pembahasan materi",
      "r": "Kutipan kalimat asli dari materi yang menjadi bukti langsung kunci jawaban"
    }
  ],
  "quiz_questions": [
    {
      "q": "Pertanyaan pilihan ganda yang menguji pemahaman, analisis, atau penerapan isi materi?",
      "a": "Pilihan A",
      "b": "Pilihan B",
      "c": "Pilihan C",
      "d": "Pilihan D",
      "k": "A",
      "e": "Penjelasan lengkap MENGAPA jawaban tersebut benar berdasarkan konsep/fakta materi, bukan sekadar menyalin judul sub-bab."
    }
  ]
}

ATURAN KETAT:
1. questions: Wajib 5 butir esai kritis berjenjang Taksonomi Bloom (mencakup level d: "easy", "medium", "hard").
   - DILARANG mengawali pertanyaan dengan "Berdasarkan teks materi..." atau "Menurut bacaan di atas...". Langsung tanyakan inti konsep, hubungan, atau analisis kasusnya!
   - Field "c" (konteks) berdiri sendiri sebagai panduan di bawah soal, jangan dimasukkan ke "q".
   - Field "h" (kunci jawaban) WAJIB ditulis sebagai parafrase yang rapi dan natural dengan bahasa Anda sendiri (bukan menyalin mentah kalimat tutorial/PDF atau memuat frasa "seperti gambar di bawah") namun 100% akurat sesuai pembahasan materi dan utuh menjawab "q".
2. quiz_questions: Wajib 10 butir pilihan ganda yang tersebar dari awal, tengah, hingga akhir materi.
   - Uji daya nalar, analisis konsep, hubungan sebab-akibat, fungsi, dan fakta penting.
   - DILARANG membuat soal dari daftar tujuan pembelajaran (seperti "Menjelaskan pengertian...") atau daftar pustaka.
   - Field "e" (Pembahasan Kunci Jawaban) WAJIB menjelaskan alasan kebenaran kunci jawaban secara jelas dan edukatif (2-3 kalimat), BUKAN sekadar menulis "Kalimat lengkap berbunyi...".
3. Kunci 'k' adalah tepat satu huruf: A, B, C, atau D. Keempat opsi (a,b,c,d) harus unik dan pengecohnya masuk akal.
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

                $questions[] = [
                    'sequence' => $seq,
                    'skill' => (string) ($q['s'] ?? $q['skill'] ?? $defaultEssaySkills[$seq] ?? 'Menjelaskan alasan'),
                    'prompt' => $prompt,
                    'context' => (string) ($q['c'] ?? $q['context'] ?? 'Uraikan konsep inti beserta contoh atau alasan logisnya.'),
                    'hint' => (string) ($q['h'] ?? $q['hint'] ?? 'Jelaskan definisi/konsep inti dan keterkaitannya secara runtut.'),
                    'reference_excerpt' => (string) ($q['r'] ?? $q['reference_excerpt'] ?? ''),
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

                $quizQuestions[] = [
                    'sequence' => $seq,
                    'question' => $qText,
                    'options' => $options,
                    'correct_answer' => $correctKey,
                    'explanation' => (string) ($qq['e'] ?? $qq['explanation'] ?? 'Pembahasan berdasarkan konsep utama dalam materi.'),
                ];
                if ($seq >= 10) {
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
                $sampleText = $this->buildFullCoverageContext($formattedBody, 15000);
                $instructions = <<<INSTRUCTIONS
Kamu adalah Perancang Soal Berpikir Kritis SocratesRead AI.
Tugasmu: analisis KESELURUHAN materi secara mendalam lalu buat tepat 5 rekomendasi soal ESAI untuk Tantangan Guru yang benar-benar menguji pemahaman kritis dan daya pikir siswa.

Format output WAJIB HANYA berupa JSON array valid:
[
  {
    "difficulty": "easy",
    "question": "Pertanyaan kritis dan langsung tentang inti materi (tanpa awalan 'Berdasarkan teks materi...' dan tanpa Konteks terpisah)",
    "rubric": "Kunci jawaban model berparafrase rapi yang LANGSUNG, LENGKAP, dan EKSPLISIT menjawab seluruh poin yang ditanyakan pada 'question' berdasarkan isi materi (bukan kutipan terpotong atau kalimat instruksi editor yang tidak nyambung)",
    "reference_excerpt": "Kutipan kalimat asli paling tepat dari materi yang menjadi bukti langsung kunci jawaban"
  }
]

ATURAN WAJIB:
1. Buat tepat 5 soal dengan distribusi level kesulitan: 1–2 "easy" (pemahaman konsep/definisi esensial), 2 "medium" (analisis fungsi, alasan, perbandingan, hubungan sebab-akibat), 1–2 "hard" (mekanisme operasional, studi kasus, evaluasi kritis, atau strategi penerapan nyata).
2. DILARANG memakai frasa canggung seperti "Berdasarkan teks materi...", "Menurut bacaan di atas...", atau menambahkan label "Konteks:". Jangan pernah menyertakan field konteks terpisah.
3. DILARANG mengambil butir tujuan pembelajaran (seperti "Menjelaskan pengertian...") atau daftar pustaka sebagai soal atau jawaban.
4. KESESUAIAN SOAL & KUNCI JAWABAN WAJIB 100%: Jika "question" menanyakan fungsi dan gagasan utama, maka "rubric" HARUS langsung menerangkan fungsi dan gagasan utama tersebut secara jelas dengan parafrase yang rapi. Jika menanyakan alasan ("Mengapa..."), "rubric" WAJIB menjelaskan penyebab serta solusinya sesuai materi.
5. Field "reference_excerpt" WAJIB berupa kutipan kalimat asli dari materi yang tepat mendukung "question" & "rubric" (bukan bagian acak atau tombol keluar editor seperti ctrl+x kecuali soal memang menanyakan cara keluar editor).
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
                        $diff = strtolower(trim((string) ($item['difficulty'] ?? 'medium')));
                        if (! in_array($diff, ['easy', 'medium', 'hard'], true)) {
                            $diff = 'medium';
                        }
                        $results[] = [
                            'type' => 'essay',
                            'question' => $q,
                            'context' => null,
                            'rubric' => $rubric,
                            'reference_excerpt' => trim((string) ($item['reference_excerpt'] ?? $item['r'] ?? '')),
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
        $sampleText = $this->buildFullCoverageContext($cleaned, 15000);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Kurator Literasi & Pembuat Soal Kritis SocratesRead AI.
Buat {$count} Soal Pilihan Ganda yang menguji pemahaman mendalam, logika berpikir kritis, hubungan sebab-akibat, serta fakta konseptual dari KESELURUHAN isi materi bacaan (awal, tengah, hingga akhir) dalam format JSON array:
[
  {
    "q": "Pertanyaan analitis atau konseptual langsung tentang isi materi?",
    "a": "Pilihan A",
    "b": "Pilihan B",
    "c": "Pilihan C",
    "d": "Pilihan D",
    "k": "A",
    "e": "Penjelasan lengkap MENGAPA pilihan tersebut benar dan bagaimana konsep tersebut dijelaskan dalam materi (2-3 kalimat informatif)."
  }
]
ATURAN WAJIB:
1. Wajib {$count} butir soal tersebar merata dari seluruh bagian materi.
2. Setiap soal harus menguji daya pikir & pemahaman materi (definisi konseptual, perbandingan, fungsi, mekanisme kerja, dasar hukum/pasal, tujuan, implikasi, atau penerapan kasus).
3. DILARANG membuat soal dari daftar tujuan pembelajaran (seperti "Menjelaskan pengertian...") atau daftar pustaka.
4. DILARANG membuat soal rumpang mekanis atau pembahasan yang hanya menyalin ulang kalimat singkat (seperti "Kalimat lengkap dalam materi berbunyi: ...").
5. Field "e" (Pembahasan Kunci Jawaban) HARUS jelas menerangkan alasan kunci jawaban benar agar membantu siswa belajar, baik saat jawabannya benar maupun salah.
6. Keempat opsi (a, b, c, d) wajib unik dan logis dengan tepat 1 kunci benar ('k': A, B, C, atau D).
7. Keluarkan HANYA JSON array tanpa teks lain.
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

            $results[] = [
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
            if ($seq >= $count) {
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

        // 1. Pengertian & Konsep Utama (Easy)
        $s1 = $pickSentence(
            fn (string $s, int $idx) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu|dengan demikian)\b/iu', $s)
                && (preg_match('/\b(?:adalah|merupakan|yaitu|didefinisikan|memakai AI|berbasis|konsep)\b/iu', $s) || $idx === ($isSocratic && $total > 6 ? 1 : 0)),
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
                $q1Prompt = $isSocratic
                    ? "Mengapa gagasan \"{$subj}\" menjadi landasan penting dalam pembahasan ini, dan nilai inti apa yang dibawanya?"
                    : "Apa yang dimaksud dengan {$subj}, dan gagasan pokok apa yang dijelaskan di dalamnya?";
            } elseif (
                preg_match('/\b(Sub-?Domain|VirtualHost|SocratesRead|Mangrove|Trigatra)\b/iu', $s1, $tm)
                && preg_match('/\b(?:menghemat|berfungsi|fungsi|tujuan|untuk|guna|agar|sehingga|memadukan|ditambahkan)\b/iu', $q1Ref)
            ) {
                $subj = $tm[1];
                $q1Subject = $subj;
                $q1Role = 'function_overview';
                $q1Prompt = $isSocratic
                    ? "Mengapa penggunaan {$subj} diperlukan dalam permasalahan yang diangkat materi ini? Jelaskan alasan logisnya!"
                    : "Apa fungsi dan gagasan utama penggunaan {$subj} sebagaimana dijelaskan pada awal materi?";
            } elseif (preg_match('/\b(Target\s+[^,.]{6,45}|Algoritma\s+RSA[^,.]{0,20}|Enkripsi\s+[^,.]{4,35}|Arsitektur\s+[^,.]{4,35})\b/iu', $s1, $tm2)) {
                $subj = trim(preg_replace('/\s+(?:yang|pada|di|dalam|untuk)$/iu', '', trim($tm2[1])) ?? trim($tm2[1]));
                $q1Subject = $subj;
                $q1Role = 'concept_summary';
                $q1Prompt = $isSocratic
                    ? "Mengapa gagasan mengenai \"{$subj}\" pada awal materi menjadi poin penting untuk diperhatikan?"
                    : "Apa gagasan utama yang dijelaskan terkait \"{$subj}\" pada bagian awal materi?";
            } else {
                $q1Prompt = $isSocratic
                    ? "Menurut penalaran Anda, mengapa gagasan pokok pada pembukaan materi \"{$title}\" krusial untuk dipahami?"
                    : "Jelaskan konsep utama yang dipaparkan pada bagian awal materi \"{$title}\"!";
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

        // 2. Fungsi & Peranan Utama (Medium)
        $s2 = $pickSentence(
            fn (string $s) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu|dengan demikian|dan|atau)\b/iu', $s)
                && str_word_count($s) >= 7
                && preg_match('/\b(?:keluar\s+adalah\s+sama|membuat\s+virtualhost|fungsi|peran|tujuan|kedudukan|menghemat|mengisi|mengganti|virtualhost|melindungi|bertujuan|dirancang|memastikan|meminimalkan)\b/iu', $s),
            min($isSocratic && $total > 5 ? 2 : 1, max(0, $total - 1))
        );
        if ($s2 !== null) {
            $q2Role = 'function';
            $q2Subject = '';
            if (preg_match('/^(.{4,60}?)\s+(?:memiliki|berfungsi|berperan|bertujuan|adalah|merupakan|memerlukan|mengubah)\s+/iu', $s2, $m) && ! preg_match('/^(?:contoh|kata|jika|setelah|akan tetapi)\b/iu', trim($m[1]))) {
                $subj2 = $cleanSubject($m[1]);
                $q2Subject = $subj2;
                $q2Prompt = $isSocratic
                    ? "Apa dampak atau perubahan nyata yang dihasilkan oleh peran {$subj2} menurut uraian materi?"
                    : "Apa peran atau tujuan utama {$subj2}, dan mengapa hal tersebut penting dalam materi ini?";
            } elseif (preg_match('/\b(virtualhost|keluar\s+adalah\s+sama|sub-?domain|menumbuhkan|pelestarian|komunikasi|Agile\s+Scrum|purwarupa\s*\(MVP\)|MVP)\b/iu', $s2, $tm)) {
                $topic2 = stripos($tm[1], 'keluar') !== false || stripos($s2, 'virtualhost') !== false ? 'VirtualHost' : $tm[1];
                $q2Subject = $topic2;
                $q2Role = 'reason';
                $q2Prompt = $isSocratic
                    ? "Bagaimana solusi terkait \"{$topic2}\" membuktikan efektivitasnya dalam menyelesaikan kendala yang dibahas?"
                    : "Mengapa mekanisme terkait \"{$topic2}\" diperlukan, dan bagaimana perannya menyelesaikan masalah dalam materi?";
            } elseif (preg_match('/\bTujuan\s*:\s*/iu', $s2)) {
                $q2Role = 'purpose';
                $q2Prompt = $isSocratic
                    ? "Mengapa tujuan utama perancangan dalam materi \"{$title}\" penting untuk dicapai?"
                    : "Apa tujuan utama yang ingin dicapai dalam materi \"{$title}\", dan manfaat apa yang diberikannya?";
            } else {
                $q2Prompt = $isSocratic
                    ? "Bukti dan alasan apa yang menunjukkan bahwa mekanisme pada materi \"{$title}\" berhasil menjawab permasalahan?"
                    : "Apa fungsi dan manfaat utama dari mekanisme yang dijelaskan dalam materi \"{$title}\"?";
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
            $s3 = $pickSentence(
                fn (string $s) => ! preg_match('/^(?:contoh|sehingga|oleh karena itu)\b/iu', $s)
                    && str_word_count($s) >= 8
                    && preg_match('/\b(?:komponen|aspek|unsur|pasal|uud|terdiri|mencakup|spesifikasi|algoritma|modul|standar|arsitektur|konfigurasi|sites-availeble|a2ensite|apache)\b/iu', $s),
                min($isSocratic && $total > 5 ? 3 : 2, max(0, $total - 1))
            );
            if ($s3 !== null) {
                $q3Subject = '';
                if (preg_match('/^(.{4,60}?)\s+(?:memerlukan|mencakup|terdiri|dilindungi|berfungsi|adalah|merupakan)\s+/iu', $s3, $m) && ! preg_match('/^(?:contoh|kata|jika|setelah)\b/iu', trim($m[1]))) {
                    $subj3 = $cleanSubject($m[1]);
                    $q3Subject = $subj3;
                    $q3Prompt = $isSocratic
                        ? "Mengapa {$subj3} membutuhkan elemen atau spesifikasi tersebut agar sistem berjalan selaras?"
                        : "Komponen, aturan, atau spesifikasi apa saja yang terkait dengan {$subj3}, dan apa fungsinya?";
                } elseif (preg_match('/\b(virtualhost|apache|sites-availeble|a2ensite|portal|mail\.yahoo\.com|pasal \d+|RSA \d+-bit)\b/iu', $s3, $tm3)) {
                    $q3Subject = $tm3[1];
                    $q3Prompt = $isSocratic
                        ? "Apa signifikansi teknis dari pengaturan \"{$tm3[1]}\" terhadap kesesuaian hasil konfigurasi dalam materi?"
                        : "Bagaimana peran atau penerapan teknis terkait \"{$tm3[1]}\" sesuai isi materi?";
                } else {
                    $q3Prompt = $isSocratic
                        ? "Simpulan apa yang dapat ditarik mengenai keterkaitan antarunsur atau aturan utama dalam materi \"{$title}\"?"
                        : "Unsur, aturan, atau spesifikasi kunci apa yang membangun konsep pada materi \"{$title}\", dan bagaimana keterkaitannya?";
                }
                $q3Ref = rtrim($s3, '.') . '.';
                $q3Answer = $this->paraphraseAnswerFromSource($q3Ref, 'component', $title, $q3Prompt, $q3Subject);
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

        // 4. Cara Kerja & Mekanisme / Prosedur (Hard)
        $s4 = $pickSentence(
            fn (string $s, int $idx) => $idx >= intdiv($total, 4)
                && str_word_count($s) >= 8
                && ! preg_match('/^(?:sehingga|contoh)\b/iu', $s)
                && preg_match('/\b(?:perintah|langkah|pertama|aktifkan|salin|edit|prinsip|mengutamakan|melestarikan|menguasai|mekanisme|pengujian|mengakibatkan|memicu|menjamin|menghindari|gamifikasi|alokasi|kapasitas|pemetaan|dasbor|anti-mencontek)\b/iu', $s),
            min(max(0, intdiv($total, 2) + ($isSocratic ? 1 : 0)), max(0, $total - 1)),
            false
        );
        if ($s4 !== null) {
            $q4Role = 'procedure';
            if (preg_match('/\b(perintah|konfigurasi|folder|aktifkan|a2ensite|apache|service|salin)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Mengapa urutan eksekusi atau langkah konfigurasi pada tahap ini krusial, dan risiko apa jika dilewati?"
                    : "Bagaimana langkah prosedur atau perintah konfigurasi yang dijelaskan dalam materi, dan mengapa tahap tersebut perlu dijalankan?";
            } elseif (preg_match('/\b(prinsip|mengutamakan|melestarikan|menguasai)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Apa risiko ketimpangan yang terjadi apabila prinsip kerja tersebut diabaikan?"
                    : "Bagaimana prinsip kerja atau kaidah utama tersebut dijalankan agar tercipta keseimbangan yang diharapkan?";
            } elseif (preg_match('/\b(alokasi|kapasitas|penyimpanan|terabita|kuota|ruang)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Berdasarkan fakta kapasitas dan sumber daya pada bacaan, keputusan evaluasi apa yang perlu diambil?"
                    : "Bagaimana kondisi alokasi kapasitas atau pemanfaatan sumber daya komputasi awan yang dipaparkan, dan apa fakta pentingnya?";
            } elseif (preg_match('/\b(permainan|gamifikasi|boss battle|anti-mencontek|dasbor)\b/iu', $s4)) {
                $q4Prompt = $isSocratic
                    ? "Mengapa mekanisme gamifikasi atau kontrol kualitas tersebut efektif menjaga keterlibatan dan kejujuran belajar?"
                    : "Bagaimana mekanisme pendukung (seperti gamifikasi atau kontrol pemahaman) bekerja mempertahankan keterlibatan pengguna dalam materi?";
            } else {
                $q4Prompt = $isSocratic
                    ? "Analisis hubungan sebab-akibat yang terjadi pada mekanisme inti dalam materi \"{$title}\" beserta implikasi kritisnya!"
                    : "Bagaimana mekanisme kerja atau hubungan sebab-akibat utama yang dijelaskan dalam materi \"{$title}\", dan apa hasilnya?";
            }
            $q4Ref = rtrim($s4, '.') . '.';
            $q4Answer = $this->paraphraseAnswerFromSource($q4Ref, $q4Role, $title, $q4Prompt);
            $q4Context = $isSocratic
                ? "Uji argumen sebab-akibat dan jelaskan konsekuensinya secara runtut."
                : "Jelaskan urutan proses, aturan operasional, atau hubungan sebab-akibat secara runtut.";
        } else {
            $q4Prompt = $isSocratic
                ? "Mengapa ketelitian prosedural pada materi \"{$title}\" menentukan keberhasilan hasilnya?"
                : "Bagaimana mekanisme kerja atau alur penerapan konsep dalam materi \"{$title}\"?";
            $q4Answer = $q2Answer;
            $q4Ref = $q2Ref;
            $q4Context = "Uraikan alur mekanisme atau sebab-akibatnya.";
        }

        // 5. Manfaat, Troubleshooting, & Penerapan Praktis (Hard)
        $s5 = $pickSentence(
            fn (string $s, int $idx) => $idx >= intdiv($total * 2, 3)
                && preg_match('/\b(?:jika tidak|restart|tekan f5|periksa|contoh penggunaan|era digital|strategi|menjaga|meningkatkan|efisiensi|latensi|penerapan|indeks|mengubah AI|pelatih berpikir)\b/iu', $s),
            max(0, $total - ($isSocratic && $total > 2 ? 2 : 1))
        );
        if ($s5 !== null) {
            if (preg_match('/\b(jika tidak bisa|restart|tekan f5|periksa konfigurasi)\b/iu', $s5)) {
                $q5Prompt = $isSocratic
                    ? "Jika layanan tidak menampilkan perubahan baru saat diuji, bagaimana alur diagnostik terbaik yang paling masuk akal berdasarkan materi?"
                    : "Apa langkah pemecahan masalah (troubleshooting) yang harus dilakukan jika sistem belum berjalan atau tampilan tidak berubah saat diuji?";
                $q5Role = 'troubleshooting';
            } elseif (preg_match('/\b(era digital|strategi|menjaga|rusak|penerapan|contoh penggunaan)\b/iu', $s5)) {
                $q5Prompt = $isSocratic
                    ? "Jika dihadapkan pada tantangan praktik masa kini, refleksi strategi apa yang paling efektif untuk menjaga keutuhan konsep materi ini?"
                    : "Bagaimana bentuk penerapan nyata atau strategi menjaga keberhasilan konsep ini dalam praktik maupun tantangan modern?";
                $q5Role = 'application';
            } else {
                $q5Prompt = $isSocratic
                    ? "Refleksi atau penerapan praktis apa yang paling relevan diambil dari kesimpulan materi \"{$title}\" untuk konteks nyata?"
                    : "Apa kesimpulan dampak serta penerapan praktis yang dapat diambil dari bagian akhir materi \"{$title}\"?";
                $q5Role = 'conclusion';
            }
            $q5Ref = rtrim($s5, '.') . '.';
            $q5Answer = $this->paraphraseAnswerFromSource($q5Ref, $q5Role, $title, $q5Prompt);
            $q5Context = $isSocratic
                ? "Sertakan alasan reflektif atau solusi praktis yang berpijak pada fakta akhir bacaan."
                : "Berikan penjelasan solusi konkret atau contoh penerapan yang selaras dengan fakta pada akhir materi.";
        } else {
            $q5Prompt = $isSocratic
                ? "Bagaimana Anda merefleksikan manfaat utama materi \"{$title}\" pada situasi nyata?"
                : "Bagaimana penerapan nyata dan manfaat utama materi \"{$title}\" dalam praktik?";
            $q5Answer = $q2Answer;
            $q5Ref = $q2Ref;
            $q5Context = "Rancang penerapan konkret berdasarkan isi bacaan.";
        }

        return [
            [
                'sequence' => 1,
                'skill' => 'Pengertian & Konsep Utama',
                'prompt' => $q1Prompt,
                'context' => $q1Context,
                'hint' => $q1Answer,
                'reference_excerpt' => $q1Ref,
                'difficulty' => 'easy',
            ],
            [
                'sequence' => 2,
                'skill' => 'Fungsi & Peranan Utama',
                'prompt' => $q2Prompt,
                'context' => $q2Context,
                'hint' => $q2Answer,
                'reference_excerpt' => $q2Ref,
                'difficulty' => 'medium',
            ],
            [
                'sequence' => 3,
                'skill' => 'Karakteristik & Komponen Kunci',
                'prompt' => $q3Prompt,
                'context' => $q3Context,
                'hint' => $q3Answer,
                'reference_excerpt' => $q3Ref,
                'difficulty' => 'medium',
            ],
            [
                'sequence' => 4,
                'skill' => 'Cara Kerja & Mekanisme',
                'prompt' => $q4Prompt,
                'context' => $q4Context,
                'hint' => $q4Answer,
                'reference_excerpt' => $q4Ref,
                'difficulty' => 'hard',
            ],
            [
                'sequence' => 5,
                'skill' => 'Manfaat & Penerapan Praktis',
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
     * saat guru mengetik pertanyaan manual, memakai penyamaan semantik + intent pertanyaan.
     *
     * @return array{recommended_answer: string, reference_excerpt: string, difficulty: string, difficulty_weight: int}
     */
    private function recommendHeuristicAnswerForQuestion(string $body, string $title, string $question): array
    {
        $cleanQuestion = trim($question);
        $sentences = $this->extractSubstantiveSentences($body, $title);
        $blocks = $this->extractSectionBlocksForSearch($body, $title);
        $kvBlocks = $this->extractKeyValuePairs($body);

        // Deteksi intent pertanyaan
        $qLower = mb_strtolower($cleanQuestion);
        $intent = 'general';
        if (preg_match('/\b(?:pengertian|apa yang dimaksud|definisi|arti|konsep|maksud dari|siapa|kapan|di mana)\b/iu', $cleanQuestion)) {
            $intent = 'definition';
        } elseif (preg_match('/\b(?:mengapa|kenapa|sebab|alasan|dampak|akibat)\b/iu', $cleanQuestion)) {
            $intent = 'reason';
        } elseif (preg_match('/\b(?:fungsi|peran|manfaat|kegunaan|kedudukan|tujuan)\b/iu', $cleanQuestion)) {
            $intent = 'function';
        } elseif (preg_match('/\b(?:bagaimana|langkah|cara|prosedur|perintah|strategi|prinsip)\b/iu', $cleanQuestion)) {
            $intent = 'procedure';
        } elseif (preg_match('/\b(?:contoh|sebutkan|apa saja|macam|jenis|aspek|komponen|unsur)\b/iu', $cleanQuestion)) {
            $intent = 'example';
        }

        // Bersihkan frasa tanya umum agar fokus pencarian menyasar konsep inti yang ditanya guru
        $focusQuery = preg_replace('/\b(?:yang\s+(?:kamu|anda|kalian)\s+ketahui|menurut\s+(?:kamu|anda|pendapatmu|materi)|sejauh\s+pemahamanmu|coba\s+jelaskan|jelaskan(?:\s+secara\s+singkat)?|apa\s+yang\s+dimaksud(?:\s+dengan)?|apa\s+pengertian(?:\s+dari)?|apakah\s+pengertian|apa\s+fungsi(?:\s+dari)?|mengapa|kenapa|bagaimana|sebutkan|uraikan)\b/iu', ' ', $cleanQuestion) ?? $cleanQuestion;
        $focusQuery = trim(preg_replace('/[?.,!]+/u', ' ', $focusQuery) ?? '');
        if (mb_strlen($focusQuery) < 3) {
            $focusQuery = $cleanQuestion;
        }

        $qKeywords = $this->similarity->extractKeywords($focusQuery);
        $rawTerms = array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9]+/u', mb_strtolower($focusQuery)) ?: [],
            fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, ['yang', 'dan', 'dari', 'pada', 'atau', 'dengan', 'untuk', 'dalam', 'tentang', 'apa', 'apakah'], true)
        )));

        $titleTerms = array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9]+/u', mb_strtolower($title)) ?: [],
            fn (string $w) => mb_strlen($w) >= 3
        )));
        $specificTerms = array_values(array_diff($rawTerms, $titleTerms));

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
        $asksAboutExit = str_contains($qLower, 'ctrl') || str_contains($qLower, 'keluar') || str_contains($qLower, 'simpan');

        foreach ($candidates as $cand) {
            $text = $cand['text'];
            $tLower = mb_strtolower($text);
            $conceptMap = $this->similarity->expandConceptMap($this->similarity->extractKeywords($text), $text);

            $score = 0.0;
            // Cocokkan istilah spesifik (non-judul) lebih berat agar pertanyaan spesifik langsung mengarah ke subtopiknya
            foreach ($specificTerms as $term) {
                if (str_contains($tLower, $term) || $this->similarity->tokenMatchesConceptMap($term, $conceptMap, $text)) {
                    $score += 3.5;
                }
            }
            foreach ($qKeywords as $kw) {
                if ($this->similarity->tokenMatchesConceptMap($kw, $conceptMap, $text)) {
                    $score += in_array($kw, $titleTerms, true) ? 1.5 : 2.5;
                }
            }
            foreach ($rawTerms as $term) {
                if (str_contains($tLower, $term)) {
                    $score += 2.0;
                }
            }

            // Bonus frasa berturut-turut (2+ kata) bila cocok tepat
            if (count($rawTerms) >= 2) {
                $phrase = implode(' ', array_slice($rawTerms, 0, 3));
                if ($phrase !== '' && str_contains($tLower, $phrase)) {
                    $score += 3.5;
                }
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

        // 1. Gabungkan anak kalimat sebab-akibat yang terputus titik ("... . Sehingga ...")
        $text = preg_replace('/\.\s*Sehingga\s+(?:kita|kami)\s+lebih\s+menghemat\s+([^.]+)/iu', ', sehingga penggunaan $1 menjadi lebih hemat', $text) ?? $text;
        $text = preg_replace('/\.\s*(Sehingga|Akibatnya|Dengan demikian)\s+/iu', ', $1 ', $text) ?? $text;
        $text = preg_replace('/^(?:Tujuan\s*:\s*)/iu', 'Tujuan utamanya adalah ', $text) ?? $text;
        $text = preg_replace('/^(?:Akan tetapi|Namun)\s+/iu', '', $text) ?? $text;

        // 2. Ubah ungkapan tutorial/informal dan hapus referensi visual lokasi PDF
        $patterns = [
            '/\bIp\s+Address\b/iu' => 'IP Address',
            '/\bdirectori\b/iu' => 'direktori',
            '/\bmenganti\b/iu' => 'mengganti',
            '/\bmengarahkanya\b/iu' => 'mengarahkannya',
            '/\bdenga\s+/iu' => 'dengan ',
            '/\bmasukan\s+perintah\b/iu' => 'masukkan perintah',
            '/\bsemisal\s+/iu' => 'seperti ',
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

        // 3. Sesuaikan pembuka jawaban agar langsung menjawab jenis pertanyaan yang diajukan
        if ($role === 'function_overview' && $subject !== '' && ! str_starts_with(mb_strtolower($text), 'fungsi')) {
            if (preg_match('/^Selain\s+domain\s+utama\s+seperti\s+([^,]+),\s*dapat\s+ditambahkan\s+pula\s+Sub-?Domain[^,]*,\s*sehingga\s+(.+)$/iu', $text, $sm)) {
                $text = "Fungsi dan gagasan utama penggunaan {$subject} adalah menambahkan domain turunan di bawah domain utama (seperti {$sm[1]}), sehingga {$sm[2]}";
            } else {
                $text = "Fungsi dan gagasan utama penggunaan {$subject} adalah bahwa {$lowerFirst}";
            }
        } elseif ($role === 'concept_summary' && $subject !== '' && ! preg_match('/^(?:Gagasan|Konsep|' . preg_quote($subject, '/') . ')/iu', $text)) {
            $text = "Gagasan utamanya adalah {$lowerFirst}";
        } elseif ($role === 'reason' && str_contains(mb_strtolower($question), 'virtualhost') && ! str_starts_with(mb_strtolower($text), 'virtualhost diperlukan')) {
            $text = "VirtualHost diperlukan karena {$lowerFirst}";
        } elseif ($role === 'troubleshooting' && ! preg_match('/^(?:Apabila|Jika|Untuk|Pengujian|Langkah)/iu', $text)) {
            $text = 'Untuk menangani kendala tampilan atau akses, ' . $lowerFirst;
        } elseif ($role === 'procedure' && preg_match('/^(?:Berkas|Salin|Gunakan|Edit)\b/iu', $text)) {
            $text = 'Pada tahap konfigurasi ini, ' . $lowerFirst;
        }

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

        // Distribusi merata putaran demi putaran lintas kalimat, sambil selalu menolak duplikat
        $maxRounds = max(array_map('count', $candidatesBySentence) ?: [1]) + 2;
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
                foreach ($candidatesBySentence[$sIdx] as $cand) {
                    $qKey = mb_strtolower(trim($cand['question']));
                    if (! isset($usedQuestions[$qKey])) {
                        $usedQuestions[$qKey] = true;
                        $selectedItems[] = $cand;
                        break;
                    }
                }
            }
        }

        // Pengaman bila teks sangat mini (< 4 kalimat) namun target $count besar (mis. 15-20 soal di unit test):
        // gunakan sudut fokus pernyataan/kesimpulan berbeda dengan nomor/kata kunci unik agar TIDAK PERNAH ada soal kembar.
        $fallbackAttempt = 0;
        while (count($selectedItems) < $count && $fallbackAttempt < $count * 3) {
            $sIdx = $fallbackAttempt % $totalSentences;
            $sentence = $sentences[$sIdx];
            $otherPool = array_values(array_filter(
                array_map(fn ($s) => rtrim($s, '.'), $sentences),
                fn ($s) => mb_strtolower($s) !== mb_strtolower(rtrim($sentence, '.'))
            ));
            $angleIndex = intdiv($fallbackAttempt, $totalSentences) % 4;
            $snippet = \Illuminate\Support\Str::limit($sentence, 52, '...');
            $qPrompt = match ($angleIndex) {
                0 => "Analisis fakta nomor " . (count($selectedItems) + 1) . ": pernyataan manakah yang selaras dengan pembahasan \"{$snippet}\"?",
                1 => "Simpulan apakah yang paling sesuai dengan gagasan pada materi terkait \"{$snippet}\"?",
                2 => "Berdasarkan uraian \"{$snippet}\", prinsip atau langkah manakah yang tepat?",
                default => "Manakah rumusan konsep berikut yang terbukti benar sesuai materi \"{$snippet}\"?",
            };
            $qKey = mb_strtolower($qPrompt);
            if (! isset($usedQuestions[$qKey])) {
                $usedQuestions[$qKey] = true;
                $selectedItems[] = [
                    'question' => $qPrompt,
                    'correct' => rtrim($sentence, '.'),
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
                $predicatePool
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
        $lineBoilerplate = '/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you|halaman|page\s*\d+|lembar kerja|latar belakang|ringkasan eksekutif|rumusan masalah|proposal perancangan|menjelaskan pengertian|mengidentifikasi kedudukan|menunjukkan sikap|menyusun strategi)/iu';
        $biblioPattern = '/^[A-Z][\p{L}\-]+,\s*[A-Z]\..*\(\d{4}\)/u';
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
            $line = trim($line);

            if ($line === '') {
                $keptLines[] = '';
                continue;
            }

            if (preg_match('/^(?:SUMBER RUJUKAN|DAFTAR PUSTAKA|REFERENSI)$/iu', $line)) {
                $inBibliographySection = true;
                $inObjectiveSection = false;
                continue;
            }
            if (preg_match('/^(?:TUJUAN PEMBELAJARAN)$/iu', $line)) {
                $inObjectiveSection = false; // Slide heading berada setelah daftar tujuan
                continue;
            }
            if (preg_match('/^Setelah mengikuti pembelajaran/iu', $line)) {
                $inObjectiveSection = true;
                continue;
            }
            if (preg_match('/^(?:DEFINISI|3 ASPEK|BAHASA INDONESIA|BAHASA DAERAH|BAHASA ASING|KESEIMBANGAN|BAB\s+)/iu', $line)) {
                $inObjectiveSection = false;
                $inBibliographySection = false;
            }

            if ($inBibliographySection || $inObjectiveSection) {
                if (preg_match($biblioPattern, $line) || preg_match($lineBoilerplate, preg_replace('/^[\-\•\d\.\s]+/u', '', $line))) {
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
            if (preg_match($biblioPattern, $line) || preg_match($biblioPattern, $lineNoBullet)) {
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
                $s = trim(preg_replace('/\s+\d+\.?$/u', '', $s) ?? '');
                $s = trim($s, "\"“”' ");

                if ($s === '' || mb_strlen($s) < 28 || mb_strlen($s) > 540) {
                    continue;
                }
                if (preg_match($lineBoilerplate, $s) || preg_match($biblioPattern, $s)) {
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
     * Membangun seluruh kandidat soal pilihan ganda analitis & konseptual unik dari satu kalimat materi.
     *
     * @param array<int, string> $allSentences
     * @param array<int, string> $termPool
     * @param array<int, string> $predicatePool
     * @return array<int, array{question: string, correct: string, distractors: array<int, string>, explanation: string}>
     */
    private function buildCandidateQuestionsFromSentence(
        string $sentence,
        array $allSentences,
        array $termPool,
        array $predicatePool,
        string $title = ''
    ): array {
        $candidates = [];

        // Angle 1: Fakta Angka / Persentase / Satuan / Pasal Hukum
        if (preg_match('/\b(Pasal\s+\d+(?:\s+ayat\s*\(\d+\))?)\b/iu', $sentence, $pasalMatch)) {
            $pasal = trim($pasalMatch[1]);
            $masked = preg_replace('/' . preg_quote($pasal, '/') . '/u', 'Pasal [...]', $sentence, 1);
            $candidates[] = [
                'question' => "Landasan hukum atau pasal UUD yang mengatur ketentuan berikut: \"{$masked}\" adalah...",
                'correct' => $pasal,
                'distractors' => ['Pasal 27', 'Pasal 28E', 'Pasal 31', 'Pasal 33', 'Pasal 34', 'Pasal 35'],
                'explanation' => "Pembahasan: Ketentuan tersebut diatur secara eksplisit dalam {$pasal} sesuai pernyataan: \"{$sentence}\"",
            ];
        }

        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*(?:-\s*bit|%|persen|Terabita|Gigabita|Megabita|TB|GB|MB|KB|transaksi(?:\s+per\s+detik)?|detik|menit|jam|hari|tahun)\b/iu', $sentence, $numMatches)) {
            foreach ($numMatches[0] as $rawToken) {
                $token = trim($rawToken);
                $masked = preg_replace('/' . preg_quote($token, '/') . '/u', '[ ... ]', $sentence, 1);
                if ($masked && $masked !== $sentence) {
                    $candidates[] = [
                        'question' => "Berapa nilai atau besaran teknis yang tepat pada kondisi berikut: \"{$masked}\"?",
                        'correct' => $token,
                        'distractors' => $this->generateNumericDistractors($token),
                        'explanation' => "Pembahasan: Besaran yang tepat adalah {$token}, sebagaimana dijelaskan pada fakta materi: \"{$sentence}\"",
                    ];
                }
            }
        }

        // Angle 2: Definisi / Makna / Peran / Cakupan
        if (preg_match('/^(.{4,65}?)\s+(adalah|merupakan|yaitu|yakni|didefinisikan sebagai|berfungsi sebagai|berperan sebagai|mencakup|terdiri dari|memiliki makna|artinya)\s+(.+)$/iu', $sentence, $m)) {
            $subject = trim($m[1], " \t\"'“”‘’");
            $verb = mb_strtolower(trim($m[2]));
            $definition = ucfirst(rtrim(trim($m[3]), '.'));

            $qPrompt = match (true) {
                str_contains($verb, 'berfungsi'), str_contains($verb, 'berperan') => "Apa peran atau fungsi utama dari \"{$subject}\"?",
                str_contains($verb, 'mencakup'), str_contains($verb, 'terdiri') => "Apa saja cakupan atau unsur dari \"{$subject}\"?",
                str_contains($verb, 'makna'), str_contains($verb, 'artinya') => "Apa makna konsep \"{$subject}\" dalam pembahasan materi?",
                default => "Apa pengertian yang paling tepat mengenai \"{$subject}\"?",
            };

            $candidates[] = [
                'question' => $qPrompt,
                'correct' => $definition,
                'distractors' => $predicatePool,
                'explanation' => "Pembahasan: Konsep \"{$subject}\" dijelaskan sebagai {$definition}. Hal ini menjadi inti pemahaman pada bagian tersebut.",
            ];
        }

        // Angle 3: Tujuan / Sasaran
        if (preg_match('/^(.{15,150}?)\s+(?:dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|untuk|guna|demi|agar)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectAction = rtrim(trim($m[1]), ', ');
            $goal = ucfirst(rtrim(trim($m[2]), '.'));
            $candidates[] = [
                'question' => "Apa tujuan utama dari \"{$subjectAction}\"?",
                'correct' => $goal,
                'distractors' => $this->buildGoalDistractors($goal, $allSentences, $predicatePool),
                'explanation' => "Pembahasan: Tindakan \"{$subjectAction}\" ditujukan untuk " . lcfirst($goal) . '.',
            ];
        }

        // Angle 3b: Temuan / Mekanisme / Hubungan Kausal
        if (preg_match('/^(.{15,140}?)\s+(mendeteksi\s+(?:adanya\s+)?|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan|disebabkan\s+oleh|karena)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectPart = rtrim(trim($m[1]), ', ');
            $connector = mb_strtolower(trim($m[2]));
            $detail = ucfirst(rtrim(trim($m[3]), '.'));

            $qPrompt = match (true) {
                str_contains($connector, 'mendeteksi') => "Temuan apa yang teridentifikasi oleh \"{$subjectPart}\"?",
                str_contains($connector, 'menunjukkan') => "Kesimpulan apa yang ditunjukkan oleh \"{$subjectPart}\"?",
                str_contains($connector, 'bertumpu') => "Pada prinsip apa \"{$subjectPart}\" bertumpu?",
                str_contains($connector, 'memerlukan') => "Kebutuhan apa yang diperlukan oleh \"{$subjectPart}\"?",
                default => "Mengapa kondisi \"{$subjectPart}\" terjadi?",
            };

            $candidates[] = [
                'question' => $qPrompt,
                'correct' => $detail,
                'distractors' => $predicatePool,
                'explanation' => "Pembahasan: Hubungan kausal/mekanisme dalam materi menjelaskan bahwa: \"{$sentence}\"",
            ];
        }

        // Angle 4: Pemahaman Konsep dari Istilah Kunci (support hingga 2 istilah unik per kalimat agar bank soal kaya dan bebas duplikat)
        $matchedTerms = [];
        foreach ($termPool as $term) {
            if (preg_match('/(?<!\p{L})' . preg_quote($term, '/') . '(?!\p{L})/iu', $sentence, $tm)) {
                $matchedTerms[] = $tm[0];
            }
        }
        $cleanSentenceCore = rtrim($sentence, '.');
        $otherSentencePool = array_values(array_filter(
            array_map(fn ($s) => rtrim($s, '.'), $allSentences),
            fn ($s) => mb_strtolower($s) !== mb_strtolower($cleanSentenceCore)
        ));

        foreach (array_slice($matchedTerms, 0, 2) as $idxTerm => $concept) {
            $conceptDistractors = array_values(array_filter(
                $otherSentencePool,
                fn ($s) => ! str_contains(mb_strtolower($s), mb_strtolower($concept))
            ));
            $promptByConcept = $idxTerm === 0
                ? "Pernyataan manakah yang menjelaskan konsep \"{$concept}\" secara tepat sesuai isi materi?"
                : "Ketentuan atau fakta manakah yang relevan dengan \"{$concept}\" dalam materi \"{$title}\"?";

            $candidates[] = [
                'question' => $promptByConcept,
                'correct' => $cleanSentenceCore,
                'distractors' => ! empty($conceptDistractors) ? $conceptDistractors : $otherSentencePool,
                'explanation' => "Pembahasan: Mengenai konsep \"{$concept}\", materi secara eksplisit memaparkan bahwa {$sentence}",
            ];
        }

        // Angle 5: Konseptual berbasis klausa pembuka + sudut verifikasi pernyataan
        $words = preg_split('/\s+/u', $sentence) ?: [];
        $splitAt = max(3, intdiv(count($words), 2));
        $head = implode(' ', array_slice($words, 0, $splitAt));
        $tail = ucfirst(rtrim(implode(' ', array_slice($words, $splitAt)), '.'));
        if (mb_strlen($tail) >= 10) {
            $candidates[] = [
                'question' => "Bagaimana penjelasan yang tepat terkait \"{$head}\"?",
                'correct' => $tail,
                'distractors' => $predicatePool,
                'explanation' => "Pembahasan: Dalam uraian materi dijelaskan bahwa: \"{$sentence}\"",
            ];
        }

        $shortHead = \Illuminate\Support\Str::limit($head, 48, '...');
        $candidates[] = [
            'question' => "Manakah pernyataan yang selaras dengan pembahasan mengenai \"{$shortHead}\"?",
            'correct' => $cleanSentenceCore,
            'distractors' => $otherSentencePool,
            'explanation' => "Pembahasan: Fakta yang sesuai dengan pembahasan tersebut berbunyi: \"{$sentence}\"",
        ];

        return $candidates;
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
        array $predicatePool
    ): array {
        $correctClean = trim($correct);
        $usedLower = [mb_strtolower($correctClean) => true];
        $distractors = [];

        $mergedPool = array_merge($candidateDistractors, $termPool, $predicatePool, [
            'Mengabaikan kaidah dan struktur konsep utama yang berlaku',
            'Menonaktifkan seluruh mekanisme verifikasi dan evaluasi sistem',
            'Mengganti standar baku dengan prosedur bebas tanpa aturan',
            'Mengurangi keandalan komunikasi dan kualitas hasil penerapan',
        ]);

        $isShortAnswer = mb_strlen($correctClean) <= 45;
        foreach ($mergedPool as $cand) {
            $c = trim((string) $cand);
            if ($c === '' || mb_strlen($c) < 2) {
                continue;
            }
            if ($isShortAnswer && mb_strlen($c) > 85) {
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

        if (count($distractors) < 3) {
            foreach ($mergedPool as $cand) {
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
                'text' => $key === $correctKey ? $correctClean : ($distractors[$dIdx++] ?? 'Opsi tidak sesuai dengan konsep materi'),
            ];
        }

        return [
            'options' => $options,
            'correct_key' => $correctKey,
        ];
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
