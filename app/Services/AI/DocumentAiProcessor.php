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
Kamu adalah Asisten Kurator Literasi SocratesRead AI.
Tugasmu adalah memproses materi teks mentah hasil ekstrak PDF dari guru untuk pembelajaran siswa.

Instruksi:
1. Rapikan teks materi ("cleaned_body"):
   - Bersihkan nomor halaman liar, header/footer buku berulang, dan spasi yang rusak.
   - Tata paragraf menjadi alur membaca yang runtut, rapi, dan nyaman dibaca (pisahkan tiap paragraf dengan dua baris baru \\n\\n).
   - Terapkan tipografi profesional: gunakan **teks tebal** untuk istilah/konsep kunci, judul bab, pasal hukum/UU, dan definisi penting. Gunakan *teks miring* untuk istilah asing, Latin, atau ilmiah.
   - Pertahankan seluruh informasi penting dan penjelasan teks asli secara utuh.
2. Buat "excerpt" (2–3 kalimat pengantar menarik yang memancing rasa ingin tahu siswa).
3. Rumuskan Soal Pilihan Ganda ("quiz_questions") BERDASARKAN FAKTA DAN SUBSTANSI SPESIFIK TEKS MATERI:
   - Soal WAJIB menguji fakta konkret, istilah teknis, nama tokoh, definisi, data/pasal, dan konsep materi yang tertulis di teks.
   - DILARANG membuat soal generik seperti "apa gagasan pokok", "bukti kalimat", "tahapan pengujian", atau "manajemen dokumen" bila materi tidak membahasnya.
   - Soal HARUS fokus pada isi materi pembelajaran di dalam file bacaan.
   - Opsi jawaban A, B, C, D harus masuk akal, diambil dari konteks materi, dan memiliki satu jawaban benar yang terbukti di teks.
   - Setiap soal WAJIB memiliki: sequence (1-10), question, options (4 opsi A, B, C, D), correct_answer ('A'/'B'/'C'/'D'), dan explanation (mengutip bukti dari teks).
4. Rumuskan 5 Pertanyaan Esai Tantangan Berpikir Kritis ("questions") BERDASARKAN SUBSTANSI MATERI (Gaya Google NotebookLM):
   - Soal HARUS menguji analisis mendalam, hubungan sebab-akibat, studi kasus nyata, dan penerapan praktis dari isi teks materi.
   - DILARANG membuat soal generik seperti "apa gagasan utama", "temukan bukti kalimat", "apa asumsi penulis", atau "jelaskan materi [Judul]".
   - Buat pertanyaan spesifik yang menyebutkan konsep, nama tokoh, undang-undang/pasal, atau fenomena nyata yang ada di dalam teks bacaan (seperti: "Mengapa [konsep A] dikategorikan sebagai [teori B]?", "Bagaimana kaitan antara [aspek X] dan [aspek Y]?", "Apa dampak yang terjadi jika [kondisi dalam teks] diabaikan?").
   - Pertanyaan 1: Definisi & Konsep Pokok Faktual.
   - Pertanyaan 2: Fungsi, Tujuan, & Peranan Kunci.
   - Pertanyaan 3: Analisis Komponen, Aspek, atau Hubungan Antargatra/Elemen.
   - Pertanyaan 4: Analisis Kasus, Dilema, atau Dampak Sebab-Akibat.
   - Pertanyaan 5: Solusi, Strategi Nyata, dan Penerapan Kontekstual Sehari-hari.
   WAJIB UNTUK FIELD "hint":
   Field "hint" HARUS BERISI KUNCI JAWABAN YANG BENAR & LENGKAP (Model Answer) yang menguraikan jawaban faktual dari teks materi.
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

        $rawResponse = $this->languageModel->respond($messages, $instructions, [
            'temperature' => 0.0,
            'max_output_tokens' => 1500,
        ]);

        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawResponse));
        $cleanJson = preg_replace('/\s*```$/', '', (string) $cleanJson);

        $parsed = json_decode((string) $cleanJson, true);

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
                if (isset($qq['options']) && is_array($qq['options'])) {
                    foreach ($qq['options'] as $opt) {
                        $options[] = [
                            'key' => (string) ($opt['key'] ?? 'A'),
                            'text' => (string) ($opt['text'] ?? ''),
                        ];
                    }
                }

                $quizQuestions[] = [
                    'sequence' => $seq,
                    'question' => (string) ($qq['question'] ?? "Pertanyaan ke-{$seq}"),
                    'options' => $options,
                    'correct_answer' => (string) ($qq['correct_answer'] ?? 'A'),
                    'explanation' => (string) ($qq['explanation'] ?? 'Pembahasan untuk soal ini.'),
                ];
                if ($seq >= 10) break;
            }
        }

        if (count($quizQuestions) < 10) {
            $heuristicQuiz = $this->generateHeuristicQuizQuestions($parsed['cleaned_body'], $title, $category);
            for ($i = count($quizQuestions) + 1; $i <= 10; $i++) {
                if (isset($heuristicQuiz[$i - 1])) {
                    $quizQuestions[] = $heuristicQuiz[$i - 1];
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
        $cleanedBody = $this->enhanceTypographyHeuristics($this->cleanText($rawText));

        $firstParagraph = collect(preg_split('/\R{2,}/u', $cleanedBody))->filter()->first() ?? $cleanedBody;
        $excerpt = mb_substr($firstParagraph, 0, 280);
        if (mb_strlen($firstParagraph) > 280) {
            $excerpt = rtrim($excerpt) . '...';
        }

        $categoryLabel = $category ? " terkait {$category}" : '';

        $questions = $this->generateHeuristicSocraticQuestions($cleanedBody, $title, $category);
        $quizQuestions = $this->generateHeuristicQuizQuestions($cleanedBody, $title, $category);

        return [
            'cleaned_body' => $cleanedBody,
            'excerpt' => $excerpt,
            'questions' => $questions,
            'quiz_questions' => $quizQuestions,
        ];
    }

    /**
     * Menghasilkan 5 Pertanyaan Esai Tantangan Pembelajaran yang berfokus substantif pada materi
     * (Pengertian, Fungsi, Komponen, Cara Kerja, dan Penerapan) beserta Kunci Jawaban Faktual (Model Answer).
     *
     * @return array<int, array{sequence: int, skill: string, prompt: string, context: string, hint: string}>
     */
    public function generateHeuristicSocraticQuestions(string $cleanedBody, string $title, ?string $category = null): array
    {
        $categoryLabel = $category ? " terkait {$category}" : '';

        // 1. Normalisasi teks dokumen dan gabungkan baris yang terputus di tengah kalimat
        $normalized = preg_replace('/(?<=[a-z])(?=[A-Z])/u', ' ', $cleanedBody);
        $normalized = preg_replace('/(?<![.?!:;\n])\R+(?=[a-z0-9A-Z])/u', ' ', $normalized);
        $normalized = preg_replace('/[ \t]+/u', ' ', $normalized);

        // 2. Ekstrak butir kalimat utuh yang substantif
        $rawSegments = collect(preg_split('/\R+/u', $normalized))
            ->map(fn ($p) => trim(preg_replace('/[*_#`]/u', '', strip_tags($p))))
            ->filter(fn ($p) => mb_strlen($p) >= 20)
            ->values();

        $meaningfulLines = [];
        foreach ($rawSegments as $seg) {
            $cleanSeg = trim(preg_replace('/^[•·\-\d+\.)\s\"\'\:\;]+/u', '', $seg));
            $cleanSeg = trim(preg_replace('/[\"\'\*\_]+$/u', '', $cleanSeg));
            $cleanSeg = trim(preg_replace('/\s+\d+[\.\)]\s*$/u', '', $cleanSeg));

            // Pisahkan jika ada beberapa kalimat utuh
            $sentences = preg_split('/(?<=[.?!])\s+/u', $cleanSeg, -1, PREG_SPLIT_NO_EMPTY) ?: [$cleanSeg];
            foreach ($sentences as $s) {
                $trimmed = trim(preg_replace('/^[•·\-\d+\.)\s\"\'\:\;]+/u', '', $s));
                $trimmed = trim(preg_replace('/[\"\'\*\_]+$/u', '', $trimmed));
                $trimmed = trim(preg_replace('/\s+\d+[\.\)]\s*$/u', '', $trimmed));
                if (mb_strlen($trimmed) >= 30 && ! preg_match('/^(?:tujuan pembelajaran|sumber rujukan|daftar pustaka|terima kasih|lembar kerja)/i', $trimmed)) {
                    $meaningfulLines[] = $trimmed;
                }
            }
        }

        $defLines = [];
        $funcLines = [];
        $compLines = [];
        $procLines = [];
        $appLines = [];

        foreach ($meaningfulLines as $l) {
            if (preg_match('/\b(?:adalah|merupakan|yaitu|yakni|didefinisikan|diartikan|konsep yang)\b/i', $l)) {
                $defLines[] = $l;
            }
            if (preg_match('/\b(?:fungsi|tujuan|guna|untuk|berfungsi|menjamin|meminimalkan|mengamankan|mengeliminasi|pelestarian|penopang)\b/i', $l)) {
                $funcLines[] = $l;
            }
            if (preg_match('/\b(?:komponen|aspek|spesifikasi|modul|arsitektur|protokol|standar|struktur|unsur|fitur|lapisan|tabel|gatra|cakupan)\b/i', $l)) {
                $compLines[] = $l;
            }
            if (preg_match('/\b(?:cara kerja|mekanisme|proses|metodologi|evaluasi|pengujian|eksekusi|optimalisasi|langkah|alur|kinerja|prinsip)\b/i', $l)) {
                $procLines[] = $l;
            }
            if (preg_match('/\b(?:penerapan|implementasi|manfaat|diterapkan|solusi|praktik|kebutuhan|dampak|contoh penggunaan)\b/i', $l)) {
                $appLines[] = $l;
            }
        }

        $lineAt = fn (int $idx, string $fallback = '') => $meaningfulLines[$idx] ?? $fallback;

        // 1. Pengertian / Konsep Inti
        $q1Answer = ! empty($defLines)
            ? implode('. ', array_slice($defLines, 0, 2))
            : ($lineAt(0, "Materi ini membahas konsep fundamental dari {$title}{$categoryLabel}."));

        // 2. Fungsi & Tujuan
        $q2Answer = ! empty($funcLines)
            ? implode('. ', array_slice($funcLines, 0, 2))
            : ($lineAt(1, "Fungsi utama dari materi ini adalah untuk memberikan pemahaman dan standarisasi dalam penerapannya."));

        // 3. Komponen / Karakteristik
        $q3Answer = ! empty($compLines)
            ? implode('. ', array_slice($compLines, 0, 2))
            : ($lineAt(2, "Aspek penting materi mencakup elemen-elemen dan spesifikasi yang diuraikan dalam bacaan."));

        // 4. Cara Kerja / Mekanisme
        $q4Answer = ! empty($procLines)
            ? implode('. ', array_slice($procLines, 0, 2))
            : ($lineAt(3, "Mekanismenya bertumpu pada alur proses dan prinsip operasional sesuai uraian bacaan."));

        // 5. Manfaat / Penerapan Praktis
        $q5Answer = ! empty($appLines)
            ? implode('. ', array_slice($appLines, 0, 2))
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
     * Menghasilkan 10 Soal Pilihan Ganda berfokus substantif pada materi bacaan nyata.
     *
     * @return array<int, array{sequence: int, question: string, options: array<int, array{key: string, text: string}>, correct_answer: string, explanation: string}>
     */
    public function generateHeuristicQuizQuestions(string $body, string $title, ?string $category = null, int $count = 10): array
    {
        $count = min(100, max(5, $count));
        
        // 1. Bersihkan dan normalisasi teks dokumen:
        // Pisahkan kata-kata CamelCase yang menempel dari PDF (misal: TrigatraBangunBahasa -> Trigatra Bangun Bahasa)
        $normalizedText = preg_replace('/(?<=[a-z])(?=[A-Z])/u', ' ', $body);

        // Pisahkan kata sambung/kunci umum bahasa Indonesia yang menempel tanpa spasi
        $gluedWords = ['adalah', 'merupakan', 'yaitu', 'oleh', 'melalui', 'dalam', 'dengan', 'untuk', 'pada', 'dari', 'yang', 'dan', 'antara', 'sesuai', 'sebagai'];
        foreach ($gluedWords as $gw) {
            $normalizedText = preg_replace('/(?<=[a-zA-Z0-9])(' . preg_quote($gw, '/') . ')(?=[a-zA-Z0-9])/ui', ' $1 ', $normalizedText);
        }

        // Gabungkan baris yang terputus di tengah kalimat akibat pemotongan PDF
        $normalizedText = preg_replace('/(?<![.?!:;\n])\R+(?=[a-z0-9A-Z])/u', ' ', $normalizedText);
        $normalizedText = preg_replace('/[ \t]+/u', ' ', $normalizedText);

        // 2. Ekstrak paragraf dan butir kalimat substantif
        $rawSegments = collect(preg_split('/\R+/u', $normalizedText))
            ->map(fn ($p) => trim(preg_replace('/[*_#`]/u', '', strip_tags($p))))
            ->filter(fn ($p) => mb_strlen($p) >= 20)
            ->values();

        $substantiveItems = collect();
        foreach ($rawSegments as $segment) {
            // Hilangkan bullet point awal
            $cleanSegment = trim(preg_replace('/^[•·\-\d+\.)\s]+/u', '', $segment));
            
            // Pisahkan kalimat berdasarkan tanda baca akhir
            $splitSentences = preg_split('/(?<=[.?!])\s+/u', $cleanSegment, -1, PREG_SPLIT_NO_EMPTY) ?: [$cleanSegment];
            foreach ($splitSentences as $s) {
                $trimmed = trim(preg_replace('/^[•·\-\d+\.)\s]+/u', '', $s));
                // Pastikan kalimat memiliki panjang yang bermakna dan lengkap
                if (mb_strlen($trimmed) >= 30 && mb_strlen($trimmed) <= 300) {
                    $substantiveItems->push($trimmed);
                }
            }
        }

        // Jika dokumen sangat singkat, pastikan ada cadangan berkualitas
        if ($substantiveItems->isEmpty()) {
            $substantiveItems->push("Materi {$title} menyajikan konsep mendasar yang perlu dipahami secara mendalam.");
            $substantiveItems->push("Penerapan materi ini bertujuan memperkuat wawasan dan kompetensi siswa.");
        }

        $itemsCount = $substantiveItems->count();
        $questions = [];

        for ($i = 1; $i <= $count; $i++) {
            $itemIdx = ($i - 1) % $itemsCount;
            $primaryItem = $substantiveItems->get($itemIdx);
            
            // Analisis struktur kalimat untuk merumuskan pertanyaan fokus materi
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
                // Ambil frasa depan sebagai kata kunci topik
                $words = preg_split('/\s+/u', $primaryItem) ?: [];
                $snippet = count($words) > 5 ? implode(' ', array_slice($words, 0, 5)) . '...' : $primaryItem;
                
                $questionTemplates = [
                    "Berdasarkan materi \"{$title}\", manakah pernyataan yang paling tepat mengenai \"{$snippet}\"?",
                    "Bagaimana teks bacaan menguraikan pembahasan terkait bagian: \"{$snippet}\"?",
                    "Manakah di antara opsi berikut yang secara akurat menjelaskan fakta pada bacaan ini?",
                    "Dalam konteks materi yang dipaparkan, apa informasi penting yang disampaikan mengenai \"{$snippet}\"?",
                ];
                $qText = $questionTemplates[($i - 1) % count($questionTemplates)];
                $correctOptionText = $primaryItem;
                $explanation = "Hal ini sesuai dengan uraian materi: \"{$primaryItem}\".";
            }

            // Putar kunci jawaban A, B, C, D secara merata
            $correctKey = match ($i % 4) {
                1 => 'A',
                2 => 'B',
                3 => 'C',
                default => 'D',
            };

            // Ambil distractor dari item lain di dokumen yang sama agar opsi tampak autentik
            $distractor1 = $substantiveItems->get(($itemIdx + 1) % $itemsCount);
            $distractor2 = $substantiveItems->get(($itemIdx + 2) % $itemsCount);
            $distractor3 = $substantiveItems->get(($itemIdx + 3) % $itemsCount);

            // Jika distractor sama dengan correct answer (karena dokumen pendek), buat variasi kontradiktif
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
            foreach (['A', 'B', 'C', 'D'] as $k) {
                $options[] = ['key' => $k, 'text' => $rawOptions[$k]];
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
