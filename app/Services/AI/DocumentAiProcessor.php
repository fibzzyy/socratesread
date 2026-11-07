<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LanguageModel;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentAiProcessor
{
    public function __construct(
        protected LanguageModel $languageModel
    ) {}

    /**
     * @return array{
     *     cleaned_body: string,
     *     excerpt: string,
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>
     * }
     */
    public function process(string $rawText, string $title, ?string $category = null): array
    {
        $cleanedFullBody = $this->cleanText($rawText);

        if ($this->languageModel->isConfigured()) {
            try {
                $aiResult = $this->processWithAi($cleanedFullBody, $title, $category);
                if ($aiResult !== null) {
                    if (mb_strlen($cleanedFullBody) > 6000) {
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
     * @return array{
     *     cleaned_body: string,
     *     excerpt: string,
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>,
     *     quiz_questions: array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     * }|null
     */
    protected function processWithAi(string $rawText, string $title, ?string $category = null): ?array
    {
        $sampleText = mb_substr(trim($rawText), 0, 5000);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Asisten Kurator Literasi SocratesRead AI.
Tugasmu adalah memproses materi teks mentah hasil ekstrak PDF dari guru untuk pembelajaran siswa.

Instruksi:
1. Rapikan teks materi ("cleaned_body"):
   - Bersihkan nomor halaman liar, header/footer buku berulang, dan spasi yang rusak.
   - Tata paragraf menjadi alur membaca yang runtut, rapi, dan nyaman dibaca (pisahkan tiap paragraf dengan dua baris baru \\n\\n).
   - Terapkan tipografi profesional: gunakan **teks tebal** untuk istilah/konsep kunci, judul bab, pasal hukum/UU, dan definisi penting. Gunakan *teks miring* untuk istilah asing, Latin, atau ilmiah.
   - Pertahankan seluruh informasi penting dan penjelasan teks asli secara utuh.
2. Buat "excerpt" (2–3 kalimat pengantar menarik yang memancing rasa ingin tahu siswa).
3. Rumuskan 10 Soal Pilihan Ganda ("quiz_questions") berdasarkan isi materi:
   - Setiap soal WAJIB memiliki: sequence (1-10), question (teks pertanyaan jelas), options (4 opsi dengan key 'A', 'B', 'C', 'D' dan text), correct_answer ('A'/'B'/'C'/'D'), dan explanation (penjelasan singkat mengapa jawaban tersebut benar).
4. Rumuskan 5 Pertanyaan Esai Sokratik Berpikir Kritis ("questions"):
   - Pertanyaan 1 (Skill: "Memahami gagasan"): Gagasan pokok / konsep kunci bacaan.
   - Pertanyaan 2 (Skill: "Menemukan bukti"): Bukti konkret / kalimat pendukung dalam teks.
   - Pertanyaan 3 (Skill: "Menganalisis asumsi"): Asumsi tersembunyi atau perspektif yang mendasari bacaan.
   - Pertanyaan 4 (Skill: "Menjelaskan alasan"): Hubungan sebab-akibat, dampak, atau implikasi materi.
   - Pertanyaan 5 (Skill: "Refleksi dan penerapan"): Studi kasus nyata atau aplikasi pemikiran siswa.
   Setiap pertanyaan esai WAJIB memiliki: sequence (1-5), skill, prompt, context, dan hint.

Format output WAJIB HANYA berupa JSON valid tanpa teks markdown pembuka/penutup lainnya:
{
  "cleaned_body": "paragraf 1...\\n\\nparagraf 2...",
  "excerpt": "Ringkasan pengantar singkat...",
  "questions": [
    {
      "sequence": 1,
      "skill": "Memahami gagasan",
      "prompt": "Pertanyaan...",
      "context": "Konteks...",
      "hint": "Petunjuk..."
    }
  ],
  "quiz_questions": [
    {
      "sequence": 1,
      "question": "Soal nomor 1...",
      "options": [
        {"key": "A", "text": "Pilihan A"},
        {"key": "B", "text": "Pilihan B"},
        {"key": "C", "text": "Pilihan C"},
        {"key": "D", "text": "Pilihan D"}
      ],
      "correct_answer": "A",
      "explanation": "Penjelasan jawaban..."
    }
  ]
}
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nMata Pelajaran/Kategori: {$category}\n\nTeks Mentah PDF:\n{$sampleText}",
            ],
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions);

        // Strip any markdown code fences if present (e.g. ```json ... ```)
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawResponse));
        $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);

        $parsed = json_decode($cleanJson, true);

        if (! is_array($parsed) || empty($parsed['cleaned_body']) || empty($parsed['questions']) || count($parsed['questions']) < 3) {
            return null;
        }

        $defaultEssaySkills = [
            1 => 'Memahami gagasan',
            2 => 'Menemukan bukti',
            3 => 'Menganalisis asumsi',
            4 => 'Menjelaskan alasan',
            5 => 'Refleksi dan penerapan',
        ];

        $questions = [];
        foreach ($parsed['questions'] as $idx => $q) {
            $seq = $idx + 1;
            $questions[] = [
                'sequence' => $seq,
                'skill' => (string) ($q['skill'] ?? $defaultEssaySkills[$seq] ?? 'Menjelaskan alasan'),
                'prompt' => (string) ($q['prompt'] ?? 'Bagaimana gagasan utama bacaan ini dijelaskan?'),
                'context' => (string) ($q['context'] ?? 'Hubungkan isi bacaan dengan pemikiranmu.'),
                'hint' => (string) ($q['hint'] ?? 'Perhatikan kalimat-kalimat penting dalam paragraf.'),
            ];
            if ($seq >= 5) break;
        }

        // If fewer than 5 essay questions from AI, top up with heuristic ones
        if (count($questions) < 5) {
            $heuristic = $this->processWithHeuristics($parsed['cleaned_body'], $title, $category);
            for ($i = count($questions) + 1; $i <= 5; $i++) {
                if (isset($heuristic['questions'][$i - 1])) {
                    $questions[] = $heuristic['questions'][$i - 1];
                }
            }
        }

        $quizQuestions = [];
        if (! empty($parsed['quiz_questions']) && is_array($parsed['quiz_questions'])) {
            foreach ($parsed['quiz_questions'] as $idx => $qq) {
                $seq = $idx + 1;
                $options = [];
                if (! empty($qq['options']) && is_array($qq['options'])) {
                    foreach ($qq['options'] as $opt) {
                        if (is_array($opt) && isset($opt['key'], $opt['text'])) {
                            $options[] = ['key' => strtoupper(trim((string) $opt['key'])), 'text' => trim((string) $opt['text'])];
                        }
                    }
                }
                if (count($options) >= 2) {
                    $quizQuestions[] = [
                        'sequence' => $seq,
                        'question' => (string) ($qq['question'] ?? "Pertanyaan nomor {$seq}"),
                        'options' => $options,
                        'correct_answer' => strtoupper(trim((string) ($qq['correct_answer'] ?? 'A'))),
                        'explanation' => (string) ($qq['explanation'] ?? 'Jawaban didasarkan pada isi bacaan.'),
                    ];
                }
                if ($seq >= 10) break;
            }
        }

        // If fewer than 10 quiz questions from AI, top up with heuristic questions
        if (count($quizQuestions) < 10) {
            $heuristicQuiz = $this->generateHeuristicQuizQuestions($parsed['cleaned_body'], $title, $category);
            for ($i = count($quizQuestions) + 1; $i <= 10; $i++) {
                if (isset($heuristicQuiz[$i - 1])) {
                    $quizQuestions[] = $heuristicQuiz[$i - 1];
                }
            }
        }

        return [
            'cleaned_body' => (string) $parsed['cleaned_body'],
            'excerpt' => (string) ($parsed['excerpt'] ?? mb_substr((string) $parsed['cleaned_body'], 0, 250)),
            'questions' => $questions,
            'quiz_questions' => $quizQuestions,
        ];
    }

    public function cleanText(string $rawText): string
    {
        $lines = preg_split('/\R/u', $rawText);
        $cleanLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $cleanLines[] = '';
                continue;
            }

            // Exclude isolated page numbers: "Halaman 12", "Hal 5", "12", "Page 3 of 10"
            if (preg_match('/^(?:halaman|hal\.?|page)?\s*\d+(?:\s*(?:of|\/)\s*\d+)?$/i', $trimmed)) {
                continue;
            }

            $cleanLines[] = $trimmed;
        }

        $cleaned = implode("\n", $cleanLines);
        $cleaned = preg_replace('/\R{3,}/u', "\n\n", $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned !== '' ? $cleaned : trim($rawText);
    }

    public function enhanceTypographyHeuristics(string $text): string
    {
        // 1. Emphasize chapter headers: "BAB I: ...", "BAB 1 - ..."
        $text = preg_replace('/^(BAB\s+[IVXLCDM\d]+[^\n]*)$/miu', '**$1**', $text) ?? $text;

        // 2. Emphasize legal articles: "Pasal 33 ayat (1)", "Pasal 27"
        $text = preg_replace('/(?<!\*)\b(Pasal\s+\d+(?:\s+ayat\s*\(\d+\))?)\b(?!\*)/iu', '**$1**', $text) ?? $text;

        // 3. Italicize common Latin/foreign phrases in academic/legal texts:
        $text = preg_replace('/(?<!\*)\b(de facto|de jure|a priori|a posteriori|et al\.|ad hoc|status quo|vice versa|mutatis mutandis)\b(?!\*)/iu', '*$1*', $text) ?? $text;

        return $text;
    }

    /**
     * @return array{
     *     cleaned_body: string,
     *     excerpt: string,
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>,
     *     quiz_questions: array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     * }
     */
    public function processWithHeuristics(string $rawText, string $title, ?string $category = null): array
    {
        $cleanedBody = $this->enhanceTypographyHeuristics($this->cleanText($rawText));

        // 2. Generate crisp excerpt
        $firstParagraph = collect(preg_split('/\R{2,}/u', $cleanedBody))->filter()->first() ?? $cleanedBody;
        $excerpt = mb_substr($firstParagraph, 0, 280);
        if (mb_strlen($firstParagraph) > 280) {
            $excerpt = rtrim($excerpt) . '...';
        }

        // 3. Generate high-quality Socratic template questions (5 questions)
        $categoryLabel = $category ? " terkait {$category}" : '';

        $questions = [
            [
                'sequence' => 1,
                'skill' => 'Memahami gagasan',
                'prompt' => "Apa gagasan utama atau konsep kunci yang diuraikan dalam materi \"{$title}\"{$categoryLabel}?",
                'context' => 'Fokuslah pada ide pokok yang mendasari keseluruhan penjelasan teks.',
                'hint' => 'Perhatikan bagian pembuka materi dan kalimat utama pada tiap paragraf.',
            ],
            [
                'sequence' => 2,
                'skill' => 'Menemukan bukti',
                'prompt' => 'Temukan satu atau dua kalimat dalam bacaan yang menjadi bukti terkuat untuk mendukung gagasan tersebut.',
                'context' => 'Kemukakan bukti spesifik (data, argumen, atau contoh) yang tercantum di dalam teks.',
                'hint' => 'Cari bagian teks yang memaparkan fakta, alasan konkrit, atau perbandingan.',
            ],
            [
                'sequence' => 3,
                'skill' => 'Menganalisis asumsi',
                'prompt' => 'Asumsi dasar atau sudut pandang apa yang mendasari uraian penulis dalam bacaan ini?',
                'context' => 'Telaah apakah ada hal yang dianggap benar oleh penulis tanpa pembuktian langsung.',
                'hint' => 'Perhatikan bagaimana penulis memilih kata dan sudut pandang dalam menyampaikan pesan.',
            ],
            [
                'sequence' => 4,
                'skill' => 'Menjelaskan alasan',
                'prompt' => "Mengapa konsep dalam \"{$title}\" ini penting untuk dipahami, dan bagaimana dampaknya jika dikaitkan dengan fenomena sekitar?",
                'context' => 'Hubungkan materi ini dengan pengalaman atau implikasi jangka panjangnya.',
                'hint' => 'Gunakan nalar kritis: pikirkan apa akibat atau manfaat dari penerapan prinsip dalam bacaan ini.',
            ],
            [
                'sequence' => 5,
                'skill' => 'Refleksi dan penerapan',
                'prompt' => 'Bagaimana kamu dapat menerapkan prinsip atau nilai dari bacaan ini dalam situasi nyata atau pemecahan masalah sehari-hari?',
                'context' => 'Rancang tindakan nyata atau kesimpulan pribadimu berdasarkan pembelajaran ini.',
                'hint' => 'Pikirkan solusi konkrit atau langkah praktis yang relevan dengan topik ini.',
            ],
        ];

        $quizQuestions = $this->generateHeuristicQuizQuestions($cleanedBody, $title, $category);

        return [
            'cleaned_body' => $cleanedBody,
            'excerpt' => $excerpt,
            'questions' => $questions,
            'quiz_questions' => $quizQuestions,
        ];
    }

    /**
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     */
    public function generateHeuristicQuizQuestions(string $body, string $title, ?string $category = null): array
    {
        $paragraphs = collect(preg_split('/\R{2,}/u', $body))
            ->map(fn ($p) => trim(strip_tags($p)))
            ->filter(fn ($p) => mb_strlen($p) > 20)
            ->values();

        $p1 = $paragraphs->get(0) ?? "Penjelasan mengenai {$title}.";
        $p2 = $paragraphs->get(1) ?? $p1;
        $p3 = $paragraphs->get(2) ?? $p2;

        $cat = $category ?: 'topik terkait';

        return [
            [
                'sequence' => 1,
                'question' => "Berdasarkan bacaan, apa topik atau gagasan utama yang dibahas mengenai \"{$title}\"?",
                'options' => [
                    ['key' => 'A', 'text' => "Pemahaman komprehensif mengenai konsep dan dampak {$title}"],
                    ['key' => 'B', 'text' => "Kritik tanpa dasar terhadap perkembangan {$cat}"],
                    ['key' => 'C', 'text' => "Kisah fiksi yang tidak memiliki rujukan ilmiah"],
                    ['key' => 'D', 'text' => "Instruksi teknis penggunaan alat elektronik modern"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Bacaan secara keseluruhan menguraikan konsep utama, fakta, dan prinsip penting mengenai {$title}.",
            ],
            [
                'sequence' => 2,
                'question' => "Manakah pernyataan berikut yang paling sesuai dengan penjelasan pada bagian awal materi?",
                'options' => [
                    ['key' => 'A', 'text' => "Materi mengabaikan peran penting masyarakat dalam {$cat}"],
                    ['key' => 'B', 'text' => mb_substr($p1, 0, 90) . '...'],
                    ['key' => 'C', 'text' => "Tidak ada hubungan antara fakta dan kesimpulan yang disajikan"],
                    ['key' => 'D', 'text' => "Penulis menyatakan bahwa materi ini tidak lagi relevan"],
                ],
                'correct_answer' => 'B',
                'explanation' => "Paragraf pembuka menekankan pengenalan materi dan konteks utama bacaan.",
            ],
            [
                'sequence' => 3,
                'question' => "Apa tujuan utama penulis dalam menyajikan materi \"{$title}\" ini?",
                'options' => [
                    ['key' => 'A', 'text' => "Memberikan informasi, analisis kritis, dan wawasan terstruktur kepada pembaca"],
                    ['key' => 'B', 'text' => "Menghibur pembaca dengan lelucon dan teka-teki fiksi"],
                    ['key' => 'C', 'text' => "Mempromosikan produk komersial tertentu"],
                    ['key' => 'D', 'text' => "Mengaburkan fakta ilmiah yang telah terbukti sebelumnya"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Tujuan teks akademik/literasi adalah membangun pemahaman mendalam dan nalar kritis pembaca.",
            ],
            [
                'sequence' => 4,
                'question' => "Mengapa pembaca perlu menemukan bukti nyata saat menganalisis uraian dalam bacaan?",
                'options' => [
                    ['key' => 'A', 'text' => "Agar argumen yang dibangun kuat, valid, dan dapat dipertanggungjawabkan"],
                    ['key' => 'B', 'text' => "Hanya untuk memperpanjang kalimat dalam tulisan"],
                    ['key' => 'C', 'text' => "Supaya pembaca tidak perlu memahami keseluruhan isi teks"],
                    ['key' => 'D', 'text' => "Karena fakta tidak berpengaruh terhadap kebenaran konsep"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Metode Sokratik menekankan pentingnya pembuktian teks (evidence-based reasoning) agar kesimpulan bernilai objektif.",
            ],
            [
                'sequence' => 5,
                'question' => "Berdasarkan penjelasan bacaan, bagaimana keterkaitan antara ide pokok dengan rincian pendukung?",
                'options' => [
                    ['key' => 'A', 'text' => "Rincian pendukung berdiri sendiri tanpa kaitan dengan ide pokok"],
                    ['key' => 'B', 'text' => "Rincian pendukung memperjelas, membuktikan, dan memperkuat ide pokok"],
                    ['key' => 'C', 'text' => "Ide pokok selalu bertentangan dengan bukti yang dituliskan"],
                    ['key' => 'D', 'text' => "Tidak ada hubungan fungsional di antara keduanya"],
                ],
                'correct_answer' => 'B',
                'explanation' => "Struktur paragraf yang baik menempatkan rincian pendukung untuk memperjelas dan membuktikan ide pokok.",
            ],
            [
                'sequence' => 6,
                'question' => "Pernyataan manakah yang paling mencerminkan sikap kritis seorang pembaca terhadap isi materi ini?",
                'options' => [
                    ['key' => 'A', 'text' => "Menerima seluruh pernyataan tanpa mempertanyakan alasan dan buktinya"],
                    ['key' => 'B', 'text' => "Membandingkan klaim penulis dengan fakta, data, dan logika yang runtut"],
                    ['key' => 'C', 'text' => "Menolak seluruh isi teks tanpa membaca argumennya"],
                    ['key' => 'D', 'text' => "Hanya membaca judul dan mengabaikan isi penjelasannya"],
                ],
                'correct_answer' => 'B',
                'explanation' => "Sikap berpikir kritis melibatkan analisis logis, pengujian klaim, dan verifikasi bukti di dalam teks.",
            ],
            [
                'sequence' => 7,
                'question' => "Manakah di bawah ini yang BUKAN merupakan karakteristik dari pembahasan yang disajikan dalam teks?",
                'options' => [
                    ['key' => 'A', 'text' => "Memiliki alur penjelasan yang terstruktur"],
                    ['key' => 'B', 'text' => "Menyajikan fakta dan konsep secara objektif"],
                    ['key' => 'C', 'text' => "Menggunakan data palsu untuk memanipulasi opini pembaca"],
                    ['key' => 'D', 'text' => "Mendorong pembaca untuk berpikir lebih dalam"],
                ],
                'correct_answer' => 'C',
                'explanation' => "Materi edukasi SocratesRead berpegang pada kejujuran akademik dan objektivitas informasi.",
            ],
            [
                'sequence' => 8,
                'question' => "Apa dampak yang diharapkan setelah siswa mempelajari topik \"{$title}\" secara menyeluruh?",
                'options' => [
                    ['key' => 'A', 'text' => "Mampu mengidentifikasi gagasan utama dan menerapkannya dalam penalaran kontekstual"],
                    ['key' => 'B', 'text' => "Menghafal kalimat tanpa mengerti makna di baliknya"],
                    ['key' => 'C', 'text' => "Mengabaikan permasalahan nyata di lingkungan sekitar"],
                    ['key' => 'D', 'text' => "Kehilangan motivasi untuk mengeksplorasi ilmu pengetahuan"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Pembelajaran berbasis Sokratik bertujuan menghasilkan pemahaman konseptual yang aplikatif.",
            ],
            [
                'sequence' => 9,
                'question' => "Jika dikaitkan dengan konteks bidang {$cat}, konsep materi ini berfungsi sebagai:",
                'options' => [
                    ['key' => 'A', 'text' => "Fondasi pengetahuan untuk memecahkan persoalan yang lebih kompleks"],
                    ['key' => 'B', 'text' => "Catatan pelengkap yang tidak memiliki kegunaan praktis"],
                    ['key' => 'C', 'text' => "Penghambat perkembangan teori-teori baru"],
                    ['key' => 'D', 'text' => "Materi sementara yang harus segera dilupakan"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Materi literasi mendalam berfungsi sebagai pondasi analitis untuk memecahkan masalah kontekstual.",
            ],
            [
                'sequence' => 10,
                'question' => "Kesimpulan apa yang paling tepat dirumuskan dari keseluruhan isi bacaan \"{$title}\"?",
                'options' => [
                    ['key' => 'A', 'text' => "Pemahaman mendalam terhadap konsep {$title} membutuhkan pembacaan aktif, kritis, dan reflektif"],
                    ['key' => 'B', 'text' => "Materi ini selesai dipelajari hanya dengan membaca sepintas lalu"],
                    ['key' => 'C', 'text' => "Tidak diperlukan evaluasi lanjutan terhadap pemikiran yang telah dipaparkan"],
                    ['key' => 'D', 'text' => "Konsep yang dibahas tidak memberikan kontribusi terhadap ilmu pengetahuan"],
                ],
                'correct_answer' => 'A',
                'explanation' => "Kesimpulan utama mencerminkan esensi Socratic Learning: membaca aktif, kritis, dan reflektif.",
            ],
        ];
    }
}
