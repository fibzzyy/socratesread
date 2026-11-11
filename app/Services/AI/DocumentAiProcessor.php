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
     *     questions: array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>,
     *     quiz_questions: array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
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
Kamu adalah Asisten Kurator Literasi SocratesRead AI (Gaya Google NotebookLM).
Tugasmu adalah menganalisis materi bacaan guru dan menghasilkan JSON ringkas berisi kuis pilihan ganda dan pertanyaan esai berpikir kritis (Taksonomi Bloom) yang 100% berakar pada fakta dan konsep materi bacaan.

Format output WAJIB HANYA berupa JSON:
{
  "cleaned_body": "teks materi yang rapi dengan tipografi tebal untuk istilah kunci...",
  "excerpt": "Ringkasan materi 2-3 kalimat...",
  "questions": [
    {
      "s": "Definisi & Konsep Pokok",
      "q": "Pertanyaan esai C1/C2...",
      "c": "Konteks berpikir...",
      "h": "Kunci jawaban model lengkap..."
    }
  ],
  "quiz_questions": [
    {
      "q": "Pertanyaan pilihan ganda berbasis fakta...",
      "a": "Pilihan A",
      "b": "Pilihan B",
      "c": "Pilihan C",
      "d": "Pilihan D",
      "k": "A",
      "e": "Bukti penjelasan dari teks..."
    }
  ]
}

ATURAN:
1. questions: Wajib 5 butir esai berjenjang Bloom C1-C5 (Definisi, Fungsi/Peran, Analisis/Kaitan, Studi Kasus/Dilema, Solusi/Penerapan). Kunci 'h' adalah model answer faktual dari materi.
2. quiz_questions: Wajib 10 butir pilihan ganda berakar fakta materi (tokoh, definisi, pasal/UU, kedudukan, fungsi). Kunci 'k' adalah A, B, C, atau D.
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nMata Pelajaran/Kategori: {$category}\n\nTeks Materi:\n{$sampleText}",
            ],
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.0,
            'max_output_tokens' => 3500,
            'timeout' => 120,
        ]);

        $parsed = null;
        if (preg_match('/\{[\s\S]*\}/u', trim($rawResponse), $matches)) {
            $parsed = json_decode($matches[0], true);
        }
        if (! is_array($parsed)) {
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawResponse));
            $cleanJson = preg_replace('/\s*```$/', '', (string) $cleanJson);
            $parsed = json_decode((string) $cleanJson, true);
        }

        if (! is_array($parsed) || empty($parsed['cleaned_body'])) {
            return null;
        }

        $defaultEssaySkills = [
            1 => 'Pengertian & Konsep Pokok',
            2 => 'Fungsi & Peranan Utama',
            3 => 'Analisis Komponen & Hubungan',
            4 => 'Analisis Kasus & Sebab-Akibat',
            5 => 'Solusi & Penerapan Nyata',
        ];

        $questions = [];
        if (! empty($parsed['questions']) && is_array($parsed['questions'])) {
            foreach ($parsed['questions'] as $idx => $q) {
                $seq = $idx + 1;
                $prompt = (string) ($q['q'] ?? $q['prompt'] ?? '');
                if ($prompt === '') continue;

                $questions[] = [
                    'sequence' => $seq,
                    'skill' => (string) ($q['s'] ?? $q['skill'] ?? $defaultEssaySkills[$seq] ?? 'Menjelaskan alasan'),
                    'prompt' => $prompt,
                    'context' => (string) ($q['c'] ?? $q['context'] ?? 'Hubungkan dengan uraian materi.'),
                    'hint' => (string) ($q['h'] ?? $q['hint'] ?? 'Perhatikan fakta penting dalam bacaan.'),
                ];
                if ($seq >= 5) break;
            }
        }

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
                $qText = (string) ($qq['q'] ?? $qq['question'] ?? '');
                if ($qText === '') continue;

                $options = [];
                if (isset($qq['options']) && is_array($qq['options'])) {
                    foreach ($qq['options'] as $opt) {
                        $options[] = [
                            'key' => (string) ($opt['key'] ?? 'A'),
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

                $quizQuestions[] = [
                    'sequence' => $seq,
                    'question' => $qText,
                    'options' => $options,
                    'correct_answer' => strtoupper((string) ($qq['k'] ?? $qq['correct_answer'] ?? 'A')),
                    'explanation' => (string) ($qq['e'] ?? $qq['explanation'] ?? 'Pembahasan berdasarkan fakta materi.'),
                ];
                if ($seq >= 10) break;
            }
        }

        if (count($quizQuestions) < 10) {
            $additionalQuiz = $this->generateHeuristicQuizQuestions($parsed['cleaned_body'], $title, $category, 10);
            for ($i = count($quizQuestions) + 1; $i <= 10; $i++) {
                if (isset($additionalQuiz[$i - 1])) {
                    $item = $additionalQuiz[$i - 1];
                    $item['sequence'] = $i;
                    $quizQuestions[] = $item;
                }
            }
        }

        return [
            'cleaned_body' => $parsed['cleaned_body'],
            'excerpt' => (string) ($parsed['excerpt'] ?? mb_substr($parsed['cleaned_body'], 0, 200)),
            'questions' => $questions,
            'quiz_questions' => $quizQuestions,
        ];
    }

    /**
     * Memastikan bank soal kuis dokumen memiliki minimal $targetCount soal tanpa menimpa soal yang sudah ada.
     */
    public function ensureQuizQuestionsForDocument(\App\Models\Document $document, int $targetCount): void
    {
        $targetCount = min(100, max(5, $targetCount));
        $existingCount = $document->quizQuestions()->count();

        if ($existingCount >= $targetCount) {
            return;
        }

        $needed = $targetCount - $existingCount;
        $nextSeq = (int) ($document->quizQuestions()->max('sequence') ?? 0) + 1;
        $toInsert = [];

        if ($this->languageModel->isConfigured() && $needed <= 15) {
            try {
                $aiItems = $this->generateQuizQuestionsWithAi(
                    $document->body,
                    $document->title,
                    $document->category,
                    $needed,
                    12
                );
                if (is_array($aiItems)) {
                    foreach ($aiItems as $item) {
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
            $heuristics = $this->generateHeuristicQuizQuestions($document->body, $document->title, $document->category, $targetCount);
            $offset = $existingCount + count($toInsert);
            $remaining = $needed - count($toInsert);

            for ($i = 0; $i < $remaining; $i++) {
                $idx = ($offset + $i) % max(1, count($heuristics));
                $toInsert[] = $heuristics[$idx];
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
     * Menghasilkan soal pilihan ganda murni berbasis AI (Google NotebookLM Style).
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>|null
     */
    public function generateQuizQuestionsWithAi(string $body, string $title, ?string $category = null, int $count = 10, int $timeout = 45): ?array
    {
        $cleaned = $this->cleanText($body);
        $len = mb_strlen($cleaned);
        if ($len <= 10000) {
            $sampleText = $cleaned;
        } else {
            // Ambil bagian awal, tengah, dan akhir agar seluruh isi materi terwakili
            $sampleText = mb_substr($cleaned, 0, 3500)
                . "\n...\n"
                . mb_substr($cleaned, intdiv($len, 2) - 1500, 3000)
                . "\n...\n"
                . mb_substr($cleaned, -3500);
        }

        $instructions = <<<INSTRUCTIONS
Kamu adalah Kurator Literasi SocratesRead AI (Gaya Google NotebookLM).
Buat {$count} Soal Pilihan Ganda yang menguji pemahaman langsung dari SELURUH isi materi bacaan (awal, tengah, hingga akhir) dalam format JSON ringkas:
[
  {
    "q": "Pertanyaan spesifik langsung tentang fakta/konsep isi materi?",
    "a": "Pilihan A",
    "b": "Pilihan B",
    "c": "Pilihan C",
    "d": "Pilihan D",
    "k": "A",
    "e": "Bukti penjelasan dari bacaan"
  }
]
ATURAN WAJIB:
1. Wajib {$count} butir soal yang tersebar merata dari awal, tengah, sampai akhir isi teks materi.
2. Setiap soal harus spesifik menanyakan fakta nyata isi materi (definisi, angka/besaran, istilah teknis, fungsi, mekanisme, tujuan, atau tokoh/aturan).
3. DILARANG membuat pertanyaan generik seperti "Berdasarkan materi ..., manakah pernyataan yang paling tepat" dan DILARANG menyalin kalimat utuh sebagai keempat pilihan ganda.
4. Keempat opsi (a, b, c, d) harus unik (tidak boleh ada opsi ganda yang sama) dengan 1 jawaban benar ('k' berupa huruf A, B, C, atau D) dan 3 pengecoh yang masuk akal.
5. Keluarkan HANYA JSON array tanpa teks lain.
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nKategori: {$category}\n\nTeks Materi:\n{$sampleText}",
            ]
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.1,
            'max_output_tokens' => max(2500, $count * 220),
            'timeout' => $timeout,
        ]);

        $jsonStr = trim($rawResponse);
        $data = null;
        if (preg_match('/\[[\s\S]*\]/u', $jsonStr, $matches)) {
            $data = json_decode($matches[0], true);
        }
        if (! is_array($data)) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $jsonStr);
            $clean = preg_replace('/\s*```$/', '', $clean);
            $data = json_decode($clean, true);
        }

        if (! is_array($data) || empty($data)) {
            return null;
        }

        $results = [];
        foreach ($data as $idx => $item) {
            $seq = $idx + 1;
            $qText = (string) ($item['q'] ?? $item['question'] ?? '');
            if ($qText === '') continue;

            $results[] = [
                'sequence' => $seq,
                'question' => $qText,
                'options' => [
                    ['key' => 'A', 'text' => (string) ($item['a'] ?? '')],
                    ['key' => 'B', 'text' => (string) ($item['b'] ?? '')],
                    ['key' => 'C', 'text' => (string) ($item['c'] ?? '')],
                    ['key' => 'D', 'text' => (string) ($item['d'] ?? '')],
                ],
                'correct_answer' => strtoupper((string) ($item['k'] ?? $item['correct_answer'] ?? 'A')),
                'explanation' => (string) ($item['e'] ?? $item['explanation'] ?? 'Sesuai dengan fakta materi bacaan.'),
            ];
            if ($seq >= $count) break;
        }

        return ! empty($results) ? $results : null;
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

            if (preg_match('/^(?:halaman|hal\.?|page)?\s*\d+(?:\s*(?:of|\/)\s*\d+)?$/i', $trimmed)) {
                continue;
            }

            if (preg_match('/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi dasar|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you)\b/iu', $trimmed)) {
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
        $text = preg_replace('/^(BAB\s+[IVXLCDM\d]+[^\n]*)$/miu', '**$1**', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\b(Pasal\s+\d+(?:\s+ayat\s*\(\d+\))?)\b(?!\*)/iu', '**$1**', $text) ?? $text;
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
        $cleaned = $this->cleanText($rawText);
        $body = $this->enhanceTypographyHeuristics($cleaned);

        return [
            'cleaned_body' => $body,
            'excerpt' => $this->generateHeuristicExcerpt($body, $title),
            'questions' => $this->generateHeuristicQuestions($body, $title, $category),
            'quiz_questions' => $this->generateHeuristicQuizQuestions($body, $title, $category, 10),
        ];
    }

    public function generateHeuristicExcerpt(string $body, string $title): string
    {
        $paragraphs = collect(preg_split('/\R{2,}/u', $body))
            ->map(fn ($p) => trim(preg_replace('/[*_#`]/u', '', strip_tags($p))))
            ->filter(fn ($p) => mb_strlen($p) >= 40)
            ->values();

        if ($paragraphs->isNotEmpty()) {
            return mb_substr($paragraphs->first(), 0, 180) . '...';
        }

        return "Pelajari konsep mendalam mengenai {$title} untuk memperluas pemahaman dan keterampilan berpikir kritis.";
    }

    /**
     * @return array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>
     */
    public function generateHeuristicSocraticQuestions(string $body, string $title, ?string $category = null): array
    {
        return $this->generateHeuristicQuestions($body, $title, $category);
    }

    /**
     * @return array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>
     */
    public function generateHeuristicQuestions(string $body, string $title, ?string $category = null): array
    {
        $bulletPattern = '/^[•·\-\d+\.)\s\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2192}\x{2713}\x{2714}⚬○■□▪▫◆◇]+/u';
        $boilerplatePattern = '/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you|halaman|page\s*\d+)/iu';

        $lines = collect(preg_split('/\R+/u', $body))
            ->map(fn ($l) => trim(preg_replace('/[*_#`]/u', '', strip_tags($l))))
            ->map(fn ($l) => trim(preg_replace($bulletPattern, '', $l)))
            ->filter(fn ($l) => mb_strlen($l) >= 20 && ! preg_match($boilerplatePattern, $l))
            ->values();

        $lineAt = function (int $index, string $default) use ($lines): string {
            return $lines->get($index, $default);
        };

        $defLines = $lines->filter(fn ($l) => preg_match('/(?:adalah|merupakan|yaitu|didefinisikan|artinya)/iu', $l))->values();
        $funcLines = $lines->filter(fn ($l) => preg_match('/(?:fungsi|tujuan|peran|kedudukan|manfaat)/iu', $l))->values();
        $compLines = $lines->filter(fn ($l) => preg_match('/(?:aspek|unsur|komponen|bagian|faktor|karakteristik)/iu', $l))->values();
        $procLines = $lines->filter(fn ($l) => preg_match('/(?:prinsip|aturan|pasal|uud|cara|proses|alur|mekanisme)/iu', $l))->values();
        $appLines = $lines->filter(fn ($l) => preg_match('/(?:contoh|penerapan|ruang publik|penggunaan|implementasi|solusi)/iu', $l))->values();

        $categoryLabel = $category ? " dalam bidang {$category}" : '';

        // 1. Pengertian
        $q1Answer = $defLines->isNotEmpty()
            ? implode('. ', array_slice($defLines->toArray(), 0, 2))
            : ($lineAt(0, "Materi ini membahas konsep fundamental dari {$title}{$categoryLabel}."));

        // 2. Fungsi & Tujuan
        $q2Answer = $funcLines->isNotEmpty()
            ? implode('. ', array_slice($funcLines->toArray(), 0, 2))
            : ($lineAt(1, "Fungsi utama dari materi ini adalah untuk memberikan pemahaman dan standarisasi dalam penerapannya."));

        // 3. Komponen / Karakteristik
        $q3Answer = $compLines->isNotEmpty()
            ? implode('. ', array_slice($compLines->toArray(), 0, 2))
            : ($lineAt(2, "Aspek penting materi mencakup elemen-elemen dan spesifikasi yang diuraikan dalam bacaan."));

        // 4. Cara Kerja / Mekanisme
        $q4Answer = $procLines->isNotEmpty()
            ? implode('. ', array_slice($procLines->toArray(), 0, 2))
            : ($lineAt(3, "Mekanismenya bertumpu pada alur proses dan prinsip operasional sesuai uraian bacaan."));

        // 5. Manfaat / Penerapan Praktis
        $q5Answer = $appLines->isNotEmpty()
            ? implode('. ', array_slice($appLines->toArray(), 0, 2))
            : ($lineAt(4, "Penerapan nyata materi ini memberikan manfaat peningkatan efisiensi dan kualitas pemecahan masalah di bidangnya."));

        // 1. Pengertian / Konsep Inti
        $q1Prompt = $defLines->isNotEmpty()
            ? "Berdasarkan teks materi, " . rtrim((string) $defLines->first(), '.') . ". Jelaskan mengapa konsep ini menjadi gagasan pokok dalam pembahasan!"
            : "Jelaskan apa yang dimaksud dengan materi \"{$title}\" dan apa konsep pokok yang dibahas di dalamnya!";

        // 2. Fungsi & Peranan
        $q2Prompt = $funcLines->isNotEmpty()
            ? "Materi menjelaskan bahwa " . rtrim((string) $funcLines->first(), '.') . ". Bagaimana fungsi tersebut berkontribusi secara nyata pada tujuan materi?"
            : "Apa fungsi dan peranan penting dari konsep yang diuraikan dalam materi \"{$title}\"?";

        // 3. Karakteristik / Komponen
        $q3Prompt = $compLines->isNotEmpty()
            ? "Dalam materi diuraikan mengenai " . rtrim((string) $compLines->first(), '.') . ". Analisis bagaimana keterkaitan antara aspek-aspek tersebut!"
            : "Sebutkan dan jelaskan karakteristik, komponen, atau aspek-aspek utama yang membangun materi \"{$title}\"!";

        // 4. Mekanisme & Dampak Sebab-Akibat
        $q4Prompt = $procLines->isNotEmpty()
            ? "Teks memaparkan bahwa " . rtrim((string) $procLines->first(), '.') . ". Apa dampak atau akibat yang timbul jika hal tersebut tidak dijalankan semestinya?"
            : "Bagaimana alur proses, aturan, atau mekanisme kerja yang diterapkan dalam topik materi \"{$title}\"?";

        // 5. Penerapan Nyata / Studi Kasus
        $q5Prompt = $appLines->isNotEmpty()
            ? "Terkait penerapan praktis, bacaan menyoroti " . rtrim((string) $appLines->first(), '.') . ". Bagaimana kamu mengimplementasikan pemikiran ini dalam kehidupan nyata?"
            : "Bagaimana manfaat dan contoh penerapan nyata dari materi \"{$title}\" ini dalam bidang kerja atau kehidupan nyata?";

        return [
            [
                'sequence' => 1,
                'skill' => 'Pengertian & Konsep Utama',
                'prompt' => $q1Prompt,
                'context' => "Fokuskan uraianmu pada definisi konsep, ruang lingkup, dan ide utama bacaan.",
                'hint' => $q1Answer,
            ],
            [
                'sequence' => 2,
                'skill' => 'Fungsi & Peranan Utama',
                'prompt' => $q2Prompt,
                'context' => "Kemukakan fungsi spesifik dan tujuan yang ingin dicapai berdasarkan penjelasan teks.",
                'hint' => $q2Answer,
            ],
            [
                'sequence' => 3,
                'skill' => 'Karakteristik & Komponen Kunci',
                'prompt' => $q3Prompt,
                'context' => "Uraikan elemen pembangun, modul, spesifikasi teknis, atau bagian penting dari materi.",
                'hint' => $q3Answer,
            ],
            [
                'sequence' => 4,
                'skill' => 'Cara Kerja & Mekanisme',
                'prompt' => $q4Prompt,
                'context' => "Jelaskan prinsip kerja, alur proses, atau mekanisme optimasi yang dipaparkan dalam bacaan.",
                'hint' => $q4Answer,
            ],
            [
                'sequence' => 5,
                'skill' => 'Manfaat & Penerapan Praktis',
                'prompt' => $q5Prompt,
                'context' => "Rancang contoh penerapan nyata atau solusi konkret berdasarkan materi yang telah dipelajari.",
                'hint' => $q5Answer,
            ],
        ];
    }

    /**
     * Menghasilkan Soal Pilihan Ganda berfokus substantif pada isi materi bacaan nyata (tanpa templat generik).
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
        $questions = [];
        $usedQuestions = [];

        for ($i = 1; $i <= $count; $i++) {
            $round = intdiv($i - 1, $totalSentences);
            $offsetInRound = ($i - 1) % $totalSentences;

            // Sebar urutan pengambilan kalimat agar merata dari awal, tengah, hingga akhir isi materi
            if ($totalSentences > $count && $round === 0) {
                $sentenceIdx = (int) floor(($offsetInRound * $totalSentences) / $count);
            } else {
                $sentenceIdx = $offsetInRound;
            }

            $sentence = $sentences[$sentenceIdx];
            $built = $this->buildQuestionFromSentence(
                $sentence,
                $round,
                $i,
                $sentences,
                $termPool,
                $predicatePool,
                $usedQuestions
            );

            $usedQuestions[mb_strtolower($built['question'])] = true;

            $options = $this->buildUniqueQuizOptions(
                $built['correct'],
                $built['distractors'],
                $i,
                $termPool,
                $predicatePool
            );

            $questions[] = [
                'sequence' => $i,
                'question' => $built['question'],
                'options' => $options['options'],
                'correct_answer' => $options['correct_key'],
                'explanation' => $built['explanation'],
            ];
        }

        return $questions;
    }

    /**
     * Mengekstrak kalimat-kalimat isi materi yang bersih dari penomoran daftar, judul kapital, dan boilerplate.
     *
     * @return array<int, string>
     */
    private function extractSubstantiveSentences(string $body, string $title): array
    {
        $rawLines = preg_split('/\R/u', $body) ?: [];
        $lineBoilerplate = '/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you|halaman|page\s*\d+|lembar kerja|latar belakang|ringkasan eksekutif|rumusan masalah|proposal perancangan)/iu';
        $titleLower = mb_strtolower(trim($title));

        // 1. Bersihkan per baris terlebih dahulu agar baris judul / boilerplate tidak menempel ke kalimat isi
        $keptLines = [];
        foreach ($rawLines as $rawLine) {
            $line = trim(preg_replace('/[*_#`]/u', '', strip_tags($rawLine)) ?? '');
            $line = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $line) ?? $line;
            // Pisahkan kata yang menempel dari sel tabel PDF (hanya kata Indonesia panjang, bukan directive teknis seperti ServerName/DocumentRoot)
            if (! preg_match('/^(?:Server|Document|Allow|Options|Order|Script|Error|Log|Custom|VirtualHost)/i', $line)) {
                $line = preg_replace('/(?<!Socrates)(?<=\p{Ll})(?=\p{Lu})(?!(?:Name|Alias|Admin|Root|Host|Override|Links|Views|Log|Level))/u', ' ', $line) ?? $line;
                $line = preg_replace('/\bSocrates\s+Read\b/u', 'SocratesRead', $line) ?? $line;
            }
            $line = trim($line);

            if ($line === '') {
                $keptLines[] = '';
                continue;
            }
            if (mb_strtolower($line) === $titleLower) {
                continue;
            }
            if (preg_match('/\p{L}/u', $line) && mb_strtoupper($line) === $line && mb_strlen($line) < 120) {
                // Baris judul huruf kapital semua (misal "LEMBAR KERJA MAHASISWA IDENTIFIKASI KALIMAT")
                continue;
            }
            if (preg_match($lineBoilerplate, $line) && mb_strlen($line) < 90) {
                continue;
            }
            if (str_ends_with($line, ':') && mb_strlen($line) < 70) {
                // Subjudul pendek diakhiri titik dua
                continue;
            }
            if ((str_contains($line, '·') || str_contains($line, '@')) && mb_strlen($line) < 90) {
                // Baris metadata penulis/tanggal/subtema
                continue;
            }

            $keptLines[] = $line;
        }

        // 2. Gabungkan soft-wrap baris PDF tanpa menggabungkan baris yang diawali bullet/nomor baru
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

        // 3. Pecah paragraf menjadi kalimat bersih
        $bulletPattern = '/^(?:[•·\-\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2192}\x{2713}\x{2714}⚬○■□▪▫◆◇]+|\d+[\.\)])\s*/u';
        $cleanSentences = [];
        $seen = [];

        foreach ($mergedParagraphs as $para) {
            $para = preg_replace('/[ \t]+/u', ' ', $para) ?? $para;
            $split = preg_split('/(?<=[.?!])["”]?\s+(?=[A-Z0-9"“])/u', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [$para];

            foreach ($split as $part) {
                $s = trim(preg_replace($bulletPattern, '', trim($part)) ?? '');
                $s = trim(preg_replace('/\s+\d+\.?$/u', '', $s) ?? '');
                $s = trim($s, "\"“”' ");

                if ($s === '' || mb_strlen($s) < 30 || mb_strlen($s) > 360) {
                    continue;
                }
                if (preg_match('/\p{L}/u', $s) && mb_strtoupper($s) === $s) {
                    continue;
                }
                if (str_ends_with($s, '?') || str_ends_with($s, ':')) {
                    continue;
                }
                // Pastikan memiliki minimal 5 kata
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
     * Mengekstrak istilah teknis dan frasa konsep penting dari seluruh kalimat isi materi.
     *
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
            if (preg_match('/^(?:yang|dan|atau|dari|untuk|pada|dengan|dalam|oleh|sebagai|adalah|merupakan|yaitu|ini|itu|tersebut|sehingga|karena|bahwa|akan|telah|dapat|harus|lebih|sangat|secara|antara|melalui)$/iu', $clean)) {
                return;
            }
            if (preg_match('/^\d+(?:[.,]\d+)?\s*(?:-|%|bit|persen|terabita|gb|mb|kb)/iu', $clean)) {
                // Angka/besaran sudah ditangani khusus oleh Angle 1 (Numeric)
                return;
            }
            $lower = mb_strtolower($clean);
            if (! isset($seen[$lower])) {
                $seen[$lower] = true;
                $terms[] = $clean;
            }
        };

        foreach ($sentences as $s) {
            // 1. Istilah di dalam tanda kurung, misal (DDoS), (load testing), (microservices)
            if (preg_match_all('/\(([^()]{3,40})\)/u', $s, $m)) {
                foreach ($m[1] as $t) {
                    $addTerm($t);
                }
            }
            // 2. Istilah berstrip / versi / teknologi, misal Wi-Fi 6, antar-mikrolayanan
            if (preg_match_all('/\b[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)+\b/u', $s, $m)) {
                foreach ($m[0] as $t) {
                    if (! preg_match('/-nya$/iu', $t)) {
                        $addTerm($t);
                    }
                }
            }
            // 3. Frasa kata benda/istilah berhuruf kapital (2-4 kata), misal Agile Scrum, SocratesRead AI
            if (preg_match_all('/\b[A-Z][\p{L}\p{N}-]*(?:\s+[A-Z][\p{L}\p{N}-]*){1,3}\b/u', $s, $m)) {
                foreach ($m[0] as $t) {
                    $addTerm($t);
                }
            }
            // 4. Frasa teknis panjang (2-3 kata bermakna)
            if (preg_match_all('/\b(?:modul|algoritma|metodologi|arsitektur|protokol|sistem|kapasitas|infrastruktur|serangan|efisiensi|latensi|utilisasi|enkripsi|komputasi|database|basis data|jaringan|performa|indeks|tabel)\s+[\p{L}\p{N}-]+(?:\s+[\p{L}\p{N}-]+)?/iu', $s, $m)) {
                foreach ($m[0] as $t) {
                    $addTerm($t);
                }
            }
        }

        return $terms;
    }

    /**
     * Mengekstrak klausa predikat/fakta ringkas untuk pengecoh yang relevan dengan topik bacaan.
     *
     * @param array<int, string> $sentences
     * @return array<int, string>
     */
    private function extractPredicatePool(array $sentences): array
    {
        $pool = [];
        foreach ($sentences as $s) {
            if (preg_match('/^.{8,70}?\s+(?:adalah|merupakan|yaitu|yakni|dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|berfungsi\s+sebagai|berperan\s+sebagai|mendeteksi|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan)\s+(.+)$/iu', $s, $m)) {
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
     * Membangun 1 soal pilihan ganda spesifik dari kalimat materi berdasarkan sudut pandang (angle) berbeda tiap putaran.
     *
     * @param array<int, string> $allSentences
     * @param array<int, string> $termPool
     * @param array<int, string> $predicatePool
     * @param array<string, bool> $usedQuestions
     * @return array{question: string, correct: string, distractors: array<int, string>, explanation: string}
     */
    private function buildQuestionFromSentence(
        string $sentence,
        int $round,
        int $sequence,
        array $allSentences,
        array $termPool,
        array $predicatePool,
        array $usedQuestions
    ): array {
        $candidates = [];

        // Angle 1: Fakta Angka / Persentase / Satuan Teknis
        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*(?:-\s*bit|%|persen|Terabita|Gigabita|Megabita|TB|GB|MB|KB|transaksi(?:\s+per\s+detik)?|detik|menit|jam|hari|tahun)\b/iu', $sentence, $numMatches)) {
            foreach ($numMatches[0] as $rawToken) {
                $token = trim($rawToken);
                $masked = preg_replace('/' . preg_quote($token, '/') . '/u', '[ ... ]', $sentence, 1);
                if ($masked && $masked !== $sentence) {
                    $candidates[] = [
                        'question' => "Berapa nilai atau besaran yang tepat untuk melengkapi bagian [...] pada isi materi berikut?\n\"{$masked}\"",
                        'correct' => $token,
                        'distractors' => $this->generateNumericDistractors($token),
                        'explanation' => "Sesuai isi materi: \"{$sentence}\"",
                    ];
                }
            }
        }

        // Angle 2: Definisi / Peran / Cakupan
        if (preg_match('/^(.{4,65}?)\s+(adalah|merupakan|yaitu|yakni|didefinisikan sebagai|berfungsi sebagai|berperan sebagai|mencakup|terdiri dari)\s+(.+)$/iu', $sentence, $m)) {
            $subject = trim($m[1]);
            $verb = mb_strtolower(trim($m[2]));
            $definition = ucfirst(rtrim(trim($m[3]), '.'));

            $qPrompt = match (true) {
                str_contains($verb, 'berfungsi'), str_contains($verb, 'berperan') => "Apa peran atau fungsi dari \"{$subject}\" menurut isi materi?",
                str_contains($verb, 'mencakup'), str_contains($verb, 'terdiri') => "Apa saja cakupan dari \"{$subject}\" yang dijelaskan dalam materi?",
                default => "Berdasarkan isi materi, apa yang dimaksud dengan \"{$subject}\"?",
            };

            $candidates[] = [
                'question' => $qPrompt,
                'correct' => $definition,
                'distractors' => $predicatePool,
                'explanation' => "Teks materi menjelaskan bahwa {$subject} {$verb} {$definition}.",
            ];
        }

        // Angle 3: Tujuan / Sasaran (guna / untuk / demi / agar / diarahkan untuk)
        if (preg_match('/^(.{15,150}?)\s+(?:dirancang\s+(?:untuk|guna)|bertujuan\s+(?:untuk|guna)|diarahkan\s+untuk|untuk|guna|demi|agar)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectAction = rtrim(trim($m[1]), ', ');
            $goal = ucfirst(rtrim(trim($m[2]), '.'));
            $candidates[] = [
                'question' => "Berdasarkan isi bacaan, apa tujuan dari \"{$subjectAction}\"?",
                'correct' => $goal,
                'distractors' => $this->buildGoalDistractors($goal, $allSentences, $predicatePool),
                'explanation' => "Sesuai isi materi, {$subjectAction} ditujukan untuk " . lcfirst($goal) . ".",
            ];
        }

        // Angle 3b: Temuan / Mekanisme / Hubungan Kausal (mendeteksi, menunjukkan bahwa, bertumpu pada, memerlukan, karena)
        if (preg_match('/^(.{15,140}?)\s+(mendeteksi\s+(?:adanya\s+)?|menunjukkan\s+bahwa|bertumpu\s+pada|memerlukan|disebabkan\s+oleh|karena)\s+(.{15,})$/iu', $sentence, $m)) {
            $subjectPart = rtrim(trim($m[1]), ', ');
            $connector = mb_strtolower(trim($m[2]));
            $detail = ucfirst(rtrim(trim($m[3]), '.'));

            $qPrompt = match (true) {
                str_contains($connector, 'mendeteksi') => "Apa temuan yang terdeteksi oleh \"{$subjectPart}\" menurut bacaan?",
                str_contains($connector, 'menunjukkan') => "Apa yang ditunjukkan oleh \"{$subjectPart}\" berdasarkan materi?",
                str_contains($connector, 'bertumpu') => "Pada hal apa \"{$subjectPart}\" bertumpu sesuai uraian materi?",
                str_contains($connector, 'memerlukan') => "Apa yang diperlukan oleh \"{$subjectPart}\" menurut materi?",
                default => "Mengapa kondisi \"{$subjectPart}\" terjadi berdasarkan isi materi?",
            };

            $candidates[] = [
                'question' => $qPrompt,
                'correct' => $detail,
                'distractors' => $predicatePool,
                'explanation' => "Sesuai isi materi: \"{$sentence}\"",
            ];
        }

        // Angle 4: Melengkapi Istilah / Konsep Kunci dalam Kalimat (Cloze Kontekstual)
        $matchedTerms = [];
        foreach ($termPool as $term) {
            if (preg_match('/(?<!\p{L})' . preg_quote($term, '/') . '(?!\p{L})/iu', $sentence, $tm)) {
                $matchedTerms[] = $tm[0];
            }
        }
        // Tambahkan frasa nomina/istilah fallback dari kalimat jika belum ada di termPool
        if (empty($matchedTerms) && preg_match_all('/\b[\p{L}]{6,}(?:\s+[\p{L}]{5,})?\b/u', $sentence, $wm)) {
            $stopPhrase = '/^(?:dalam|dengan|untuk|sebagai|bahwa|karena|melalui|terhadap|berdasarkan|antara|menunjukkan|memerlukan|merupakan|adalah|akhirnya|semula|adanya|berupa|menjawab|benar-benar|mengarahkan|tambahkan|silahkan|kemudian|berikut|dibawah|tersebut|tersebutlah|mengikuti|perintah|beberapa)\b|\b(?:tersebut|tersebutlah|perintah|beberapa|dibawah)$/iu';
            $words = array_values(array_filter($wm[0], fn ($w) => ! preg_match($stopPhrase, $w)));
            if (! empty($words)) {
                usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
                $matchedTerms = array_slice($words, 0, 3);
            }
        }

        foreach ($matchedTerms as $mt) {
            $masked = preg_replace('/(?<!\p{L})' . preg_quote($mt, '/') . '(?!\p{L})/u', '[ ... ]', $sentence, 1);
            if ($masked && $masked !== $sentence) {
                $candidates[] = [
                    'question' => "Istilah atau konsep yang tepat untuk melengkapi bagian [...] pada kutipan materi berikut adalah:\n\"{$masked}\"",
                    'correct' => $mt,
                    'distractors' => $termPool,
                    'explanation' => "Kalimat lengkap dalam materi berbunyi: \"{$sentence}\"",
                ];
            }
        }

        // Angle 5: Pertanyaan fokus klausa akhir (tidak pernah memakai templat "Berdasarkan materi X...")
        if (empty($candidates)) {
            $words = preg_split('/\s+/u', $sentence) ?: [];
            $splitAt = max(3, intdiv(count($words), 2));
            $head = implode(' ', array_slice($words, 0, $splitAt));
            $tail = ucfirst(rtrim(implode(' ', array_slice($words, $splitAt)), '.'));
            $candidates[] = [
                'question' => "Sesuai isi materi, kelanjutan penjelasan yang tepat mengenai \"{$head}...\" adalah:",
                'correct' => $tail,
                'distractors' => $predicatePool,
                'explanation' => "Materi lengkap menyatakan: \"{$sentence}\"",
            ];
        }

        // Pilih kandidat yang belum pernah dipakai sebelumnya sesuai rotasi putaran ($round)
        $totalCand = count($candidates);
        for ($step = 0; $step < $totalCand; $step++) {
            $picked = $candidates[($round + $step) % $totalCand];
            if (! isset($usedQuestions[mb_strtolower($picked['question'])])) {
                return $picked;
            }
        }

        return $candidates[$sequence % $totalCand];
    }

    /**
     * Menghasilkan 3 pengecoh angka/satuan yang masuk akal dan pasti berbeda dari jawaban benar.
     *
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

        if ($usesDotThousand) {
            $num = (float) str_replace('.', '', $rawNum);
        } else {
            $num = (float) str_replace(',', '.', $rawNum);
        }

        // Khusus bilangan pangkat 2 seperti 2048-bit
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
     * Menghasilkan pengecoh tujuan/sasaran dari kalimat materi lain sehingga tidak ambigu.
     *
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
     * Menyusun tepat 4 pilihan jawaban (A, B, C, D) yang dijamin unik tanpa duplikasi.
     *
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
            'Meningkatkan latensi pemrosesan di seluruh komponen utama',
            'Menonaktifkan seluruh mekanisme verifikasi dan pencatatan aktivitas',
            'Mengganti protokol standar dengan akses terbuka tanpa otentikasi',
            'Mengurangi kapasitas penyimpanan aktif hingga nol persen',
        ]);

        // Jika jawaban singkat (istilah/angka), prioritaskan pengecoh yang panjangnya proporsional lebih dulu
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

        // Jaring pengaman jika masih kurang dari 3 (misal semua kandidat terlalu panjang)
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
                'text' => $key === $correctKey ? $correctClean : ($distractors[$dIdx++] ?? 'Opsi tidak relevan dengan konteks materi'),
            ];
        }

        return [
            'options' => $options,
            'correct_key' => $correctKey,
        ];
    }
}
