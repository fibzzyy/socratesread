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
            // Coba generate sisa quiz questions via AI khusus kuis atau fallback ke heuristic
            $additionalQuiz = $this->generateQuizQuestions($parsed['cleaned_body'], $title, $category, 10);
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
    public function generateQuizQuestionsWithAi(string $body, string $title, ?string $category = null, int $count = 10): ?array
    {
        $cleaned = $this->cleanText($body);
        $sampleText = mb_substr($cleaned, 0, 4500);

        $instructions = <<<INSTRUCTIONS
Kamu adalah Kurator Literasi SocratesRead AI (Gaya Google NotebookLM).
Buat {$count} Soal Pilihan Ganda dari fakta materi bacaan dalam format JSON ringkas:
[
  {
    "q": "Pertanyaan berakar fakta materi?",
    "a": "Pilihan A",
    "b": "Pilihan B",
    "c": "Pilihan C",
    "d": "Pilihan D",
    "k": "A",
    "e": "Bukti penjelasan dari bacaan"
  }
]
ATURAN: Wajib {$count} butir soal, menguji fakta nyata (tokoh, definisi, pasal UUD, kedudukan, fungsi gatra bahasa). Kunci 'k' berupa huruf A, B, C, atau D. Keluarkan HANYA JSON array tanpa teks lain.
INSTRUCTIONS;

        $messages = [
            [
                'role' => 'user',
                'content' => "Judul Materi: {$title}\nKategori: {$category}\n\nTeks Materi:\n{$sampleText}",
            ]
        ];

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.1,
            'max_output_tokens' => 2500,
            'timeout' => 90,
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
        $q1Answer = ! empty($defLines)
            ? implode('. ', array_slice($defLines->toArray(), 0, 2))
            : ($lineAt(0, "Materi ini membahas konsep fundamental dari {$title}{$categoryLabel}."));

        // 2. Fungsi & Tujuan
        $q2Answer = ! empty($funcLines)
            ? implode('. ', array_slice($funcLines->toArray(), 0, 2))
            : ($lineAt(1, "Fungsi utama dari materi ini adalah untuk memberikan pemahaman dan standarisasi dalam penerapannya."));

        // 3. Komponen / Karakteristik
        $q3Answer = ! empty($compLines)
            ? implode('. ', array_slice($compLines->toArray(), 0, 2))
            : ($lineAt(2, "Aspek penting materi mencakup elemen-elemen dan spesifikasi yang diuraikan dalam bacaan."));

        // 4. Cara Kerja / Mekanisme
        $q4Answer = ! empty($procLines)
            ? implode('. ', array_slice($procLines->toArray(), 0, 2))
            : ($lineAt(3, "Mekanismenya bertumpu pada alur proses dan prinsip operasional sesuai uraian bacaan."));

        // 5. Manfaat / Penerapan Praktis
        $q5Answer = ! empty($appLines)
            ? implode('. ', array_slice($appLines->toArray(), 0, 2))
            : ($lineAt(4, "Penerapan nyata materi ini memberikan manfaat peningkatan efisiensi dan kualitas pemecahan masalah di bidangnya."));

        // 1. Pengertian / Konsep Inti
        $q1Prompt = ! empty($defLines)
            ? "Berdasarkan teks materi, " . rtrim($defLines[0], '.') . ". Jelaskan mengapa konsep ini menjadi gagasan pokok dalam pembahasan!"
            : "Jelaskan apa yang dimaksud dengan materi \"{$title}\" dan apa konsep pokok yang dibahas di dalamnya!";

        // 2. Fungsi & Peranan
        $q2Prompt = ! empty($funcLines)
            ? "Materi menjelaskan bahwa " . rtrim($funcLines[0], '.') . ". Bagaimana fungsi tersebut berkontribusi secara nyata pada tujuan materi?"
            : "Apa fungsi dan peranan penting dari konsep yang diuraikan dalam materi \"{$title}\"?";

        // 3. Karakteristik / Komponen
        $q3Prompt = ! empty($compLines)
            ? "Dalam materi diuraikan mengenai " . rtrim($compLines[0], '.') . ". Analisis bagaimana keterkaitan antara aspek-aspek tersebut!"
            : "Sebutkan dan jelaskan karakteristik, komponen, atau aspek-aspek utama yang membangun materi \"{$title}\"!";

        // 4. Mekanisme & Dampak Sebab-Akibat
        $q4Prompt = ! empty($procLines)
            ? "Teks memaparkan bahwa " . rtrim($procLines[0], '.') . ". Apa dampak atau akibat yang timbul jika hal tersebut tidak dijalankan semestinya?"
            : "Bagaimana alur proses, aturan, atau mekanisme kerja yang diterapkan dalam topik materi \"{$title}\"?";

        // 5. Penerapan Nyata / Studi Kasus
        $q5Prompt = ! empty($appLines)
            ? "Terkait penerapan praktis, bacaan menyoroti " . rtrim($appLines[0], '.') . ". Bagaimana kamu mengimplementasikan pemikiran ini dalam kehidupan nyata?"
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
     * Menghasilkan Soal Pilihan Ganda berfokus substantif pada materi bacaan nyata.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     */
    public function generateHeuristicQuizQuestions(string $body, string $title, ?string $category = null, int $count = 10): array
    {
        $count = min(100, max(5, $count));
        
        // 1. Bersihkan dan normalisasi teks dokumen:
        $normalizedText = preg_replace('/(?<=[a-z])(?=[A-Z])/u', ' ', $body);

        $gluedWords = ['adalah', 'merupakan', 'yaitu', 'oleh', 'melalui', 'dalam', 'dengan', 'untuk', 'pada', 'dari', 'yang', 'dan', 'antara', 'sesuai', 'sebagai'];
        foreach ($gluedWords as $gw) {
            $normalizedText = preg_replace('/(?<=[a-zA-Z0-9])(' . preg_quote($gw, '/') . ')(?=[a-zA-Z0-9])/ui', ' $1 ', $normalizedText);
        }

        $normalizedText = preg_replace('/(?<![.?!:;\n])\R+(?=[a-z0-9A-Z])/u', ' ', $normalizedText);
        $normalizedText = preg_replace('/[ \t]+/u', ' ', $normalizedText);

        // 2. Ekstrak paragraf dan butir kalimat substantif
        $rawSegments = collect(preg_split('/\R+/u', $normalizedText))
            ->map(fn ($p) => trim(preg_replace('/[*_#`]/u', '', strip_tags($p))))
            ->filter(fn ($p) => mb_strlen($p) >= 20)
            ->values();

        $substantiveItems = collect();
        $bulletPattern = '/^[•·\-\d+\.)\s\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2192}\x{2713}\x{2714}⚬○■□▪▫◆◇]+/u';
        $boilerplatePattern = '/^(?:setelah mengikuti|tujuan pembelajaran|capaian pembelajaran|kompetensi|mahasiswa diharapkan|siswa diharapkan|peserta didik|daftar pustaka|sumber rujukan|terima kasih|thank you|halaman|page\s*\d+)/iu';

        foreach ($rawSegments as $segment) {
            $cleanSegment = trim(preg_replace($bulletPattern, '', $segment));
            if ($cleanSegment === '' || preg_match($boilerplatePattern, $cleanSegment)) {
                continue;
            }
            
            $splitSentences = preg_split('/(?<=[.?!])\s+/u', $cleanSegment, -1, PREG_SPLIT_NO_EMPTY) ?: [$cleanSegment];
            foreach ($splitSentences as $s) {
                $trimmed = trim(preg_replace($bulletPattern, '', $s));
                if ($trimmed === '' || preg_match($boilerplatePattern, $trimmed)) {
                    continue;
                }
                if (mb_strlen($trimmed) >= 30 && mb_strlen($trimmed) <= 300) {
                    $substantiveItems->push($trimmed);
                }
            }
        }

        if ($substantiveItems->isEmpty()) {
            $substantiveItems->push("Materi {$title} menyajikan konsep mendasar yang perlu dipahami secara mendalam.");
            $substantiveItems->push("Penerapan materi ini bertujuan memperkuat wawasan dan kompetensi siswa.");
        }

        $itemsCount = $substantiveItems->count();
        $questions = [];

        for ($i = 1; $i <= $count; $i++) {
            $itemIdx = ($i - 1) % $itemsCount;
            $primaryItem = $substantiveItems->get($itemIdx);
            
            $qText = "";
            $correctOptionText = $primaryItem;
            $explanation = "";

            if (preg_match('/^(.{3,45}?)\s+(?:adalah|merupakan|yaitu|didefinisikan sebagai)\s+(.+)$/ui', $primaryItem, $matches)) {
                $subject = trim($matches[1]);
                $definition = trim($matches[2], " .");
                $qText = "Berdasarkan materi bacaan, apa yang dimaksud dengan \"{$subject}\"?";
                $correctOptionText = ucfirst($definition);
                $explanation = "Teks materi secara eksplisit mendefinisikan \"{$subject}\" sebagai {$definition}.";
            } elseif (preg_match('/^(.{3,45}?)\s+(?:sebagai|berfungsi sebagai|berperan sebagai)\s+(.+)$/ui', $primaryItem, $matches)) {
                $subject = trim($matches[1]);
                $role = trim($matches[2], " .");
                $qText = "Dalam pembahasan \"{$title}\", apa peran atau fungsi dari \"{$subject}\"?";
                $correctOptionText = "Sebagai " . lcfirst($role);
                $explanation = "Materi menjelaskan bahwa \"{$subject}\" memiliki peran atau fungsi {$role}.";
            } elseif (preg_match('/^(.{3,35}?)\s*:\s*(.+)$/u', $primaryItem, $matches)) {
                $topic = trim($matches[1]);
                $detail = trim($matches[2], " .");
                $qText = "Terkait bahasan \"{$topic}\" pada materi ini, manakah uraian yang tepat?";
                $correctOptionText = ucfirst($detail);
                $explanation = "Sesuai rincian pada teks bacaan mengenai {$topic}, poin yang disampaikan adalah: {$detail}.";
            } else {
                $questionTemplates = [
                    "Berdasarkan materi \"{$title}\", manakah pernyataan yang paling tepat sesuai fakta bacaan?",
                    "Bagaimana teks bacaan menjelaskan poin penting terkait konsep yang dibahas?",
                    "Manakah di antara opsi berikut yang secara akurat memaparkan fakta pada materi ini?",
                    "Dalam konteks materi \"{$title}\", informasi penting apa yang ditegaskan dalam pembahasan?",
                ];
                $qText = $questionTemplates[($i - 1) % count($questionTemplates)];
                $correctOptionText = $primaryItem;
                $explanation = "Hal ini sesuai dengan uraian materi: \"{$primaryItem}\".";
            }

            $correctKey = match ($i % 4) {
                1 => 'A',
                2 => 'B',
                3 => 'C',
                default => 'D',
            };

            $distractor1 = $substantiveItems->get(($itemIdx + 1) % $itemsCount);
            $distractor2 = $substantiveItems->get(($itemIdx + 2) % $itemsCount);
            $distractor3 = $substantiveItems->get(($itemIdx + 3) % $itemsCount);

            if ($distractor1 === $primaryItem || mb_strlen((string) $distractor1) < 15) {
                $distractor1 = "Konsep ini tidak memiliki keterkaitan langsung dengan tujuan utama materi {$title}.";
            }
            if ($distractor2 === $primaryItem || $distractor2 === $distractor1 || mb_strlen((string) $distractor2) < 15) {
                $distractor2 = "Pernyataan tersebut dibantah oleh fakta dan uraian yang disampaikan dalam bacaan.";
            }
            if ($distractor3 === $primaryItem || $distractor3 === $distractor1 || $distractor3 === $distractor2 || mb_strlen((string) $distractor3) < 15) {
                $distractor3 = "Hal tersebut hanya merupakan opini alternatif yang tidak tercantum dalam isi teks.";
            }

            $rawOptions = [
                'A' => $correctKey === 'A' ? $correctOptionText : $distractor1,
                'B' => $correctKey === 'B' ? $correctOptionText : ($correctKey === 'A' ? $distractor2 : $distractor1),
                'C' => $correctKey === 'C' ? $correctOptionText : ($correctKey === 'D' ? $distractor2 : $distractor3),
                'D' => $correctKey === 'D' ? $correctOptionText : $distractor3,
            ];

            $options = [];
            foreach (['A', 'B', 'C', 'D'] as $key) {
                $options[] = [
                    'key' => $key,
                    'text' => $rawOptions[$key],
                ];
            }

            $questions[] = [
                'sequence' => $i,
                'question' => $qText,
                'options' => $options,
                'correct_answer' => $correctKey,
                'explanation' => $explanation,
            ];
        }

        return $questions;
    }
}
