<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherChallenge;
use App\Services\Evaluation\AnswerEvaluationPipeline;
use App\Services\Evaluation\AnswerHighlightService;
use App\Services\TeacherChallengeScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentTeacherChallengeController extends Controller
{
    public function show(Document $document, TeacherChallengeScoringService $scoring): View|RedirectResponse
    {
        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();

        $user = auth()->user();
        $submission = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        $totalQuestions = $challenges->count();
        $pointsMap = $scoring->distributePoints($challenges);
        $pointsPerQuestion = $totalQuestions > 0 ? round(100 / $totalQuestions, 2) : 0;
        $maxRewardXp = $scoring->calculateMaxRewardXp($challenges);
        $existingAnswers = ($submission && ! $submission->isCompleted() && is_array($submission->answers))
            ? $submission->answers
            : [];
        $checkedCount = count($existingAnswers);
        $initialActiveIndex = 0;
        foreach ($challenges as $idx => $item) {
            if (! isset($existingAnswers[$item->id])) {
                $initialActiveIndex = $idx;
                break;
            }
        }

        return view('student.teacher-challenge', compact(
            'document',
            'challenges',
            'submission',
            'existingAnswers',
            'checkedCount',
            'initialActiveIndex',
            'totalQuestions',
            'pointsPerQuestion',
            'pointsMap',
            'maxRewardXp'
        ));
    }

    /**
     * Evaluasi interaktif per soal gaya Gemini NotebookLM (mengunci hasil soal yang sudah dicek).
     */
    public function checkQuestion(
        Request $request,
        Document $document,
        AnswerEvaluationPipeline $pipeline,
        AnswerHighlightService $highlightService,
        TeacherChallengeScoringService $scoring
    ): JsonResponse {
        $validated = $request->validate([
            'challenge_id' => ['required', 'integer'],
            'answer' => ['nullable', 'string', 'max:10000'],
        ]);

        $user = $request->user();
        $submission = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        if ($submission && $submission->isCompleted()) {
            return response()->json(['message' => 'Tantangan guru ini sudah selesai dikumpulkan.'], 403);
        }

        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();
        $challenge = $challenges->firstWhere('id', (int) $validated['challenge_id']);
        if (! $challenge) {
            return response()->json(['message' => 'Soal tantangan tidak ditemukan pada materi ini.'], 404);
        }

        $existingAnswers = ($submission && is_array($submission->answers)) ? $submission->answers : [];

        // Anti-peek guard: jika soal ini sudah pernah diperiksa, kembalikan hasil yang sudah terkunci
        if (isset($existingAnswers[$challenge->id]) && is_array($existingAnswers[$challenge->id])) {
            return response()->json([
                'evaluated' => $existingAnswers[$challenge->id],
                'checked_count' => count($existingAnswers),
                'total_questions' => $challenges->count(),
                'locked' => true,
            ]);
        }

        $rawAnswer = trim((string) ($validated['answer'] ?? ''));
        if ($challenge->type === 'multiple_choice' && $rawAnswer === '') {
            return response()->json(['message' => 'Pilih salah satu opsi jawaban terlebih dahulu sebelum memeriksa.'], 422);
        }
        $words = preg_split('/\s+/u', $rawAnswer, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($challenge->type === 'essay' && (mb_strlen($rawAnswer) < 10 || count($words) < 4)) {
            return response()->json(['message' => 'Tuliskan jawaban esai minimal 4 kata sebelum menekan Periksa Jawaban.'], 422);
        }

        @set_time_limit(120);

        $pointsMap = $scoring->distributePoints($challenges);
        $evaluated = $this->evaluateSingleChallenge(
            $challenge,
            $rawAnswer,
            $document,
            $pointsMap,
            $challenges->count(),
            $pipeline,
            $highlightService
        );

        $existingAnswers[$challenge->id] = $evaluated;

        StudentTeacherChallengeSubmission::updateOrCreate(
            ['user_id' => $user->id, 'document_id' => $document->id],
            [
                'score' => 0,
                'xp_awarded' => 0,
                'answers' => $existingAnswers,
                'status' => 'in_progress',
            ]
        );

        return response()->json([
            'evaluated' => $evaluated,
            'checked_count' => count($existingAnswers),
            'total_questions' => $challenges->count(),
            'locked' => true,
        ]);
    }

    public function submit(
        Request $request,
        Document $document,
        AnswerEvaluationPipeline $pipeline,
        AnswerHighlightService $highlightService,
        TeacherChallengeScoringService $scoring
    ): JsonResponse|RedirectResponse {
        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();

        if ($challenges->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Tidak ada tantangan dari guru untuk materi ini.'], 422);
            }

            return redirect()->route('student.teacher-challenges.show', ['document' => $document->id])
                ->with('status', 'Tidak ada tantangan dari guru untuk materi ini.');
        }

        $user = $request->user();
        $submission = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        if ($submission && $submission->isCompleted()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Tantangan guru ini sudah selesai dikumpulkan.'], 403);
            }

            return redirect()->route('student.teacher-challenges.show', ['document' => $document->id])
                ->with('status', 'Tantangan guru ini sudah selesai dikumpulkan.');
        }

        @set_time_limit(180);

        $submittedAnswers = $request->input('answers', []);
        $totalQuestions = $challenges->count();
        $pointsMap = $scoring->distributePoints($challenges);
        $maxRewardXp = $scoring->calculateMaxRewardXp($challenges);

        $existingDraftAnswers = ($submission && ! $submission->isCompleted() && is_array($submission->answers))
            ? $submission->answers
            : [];

        $answersBreakdown = [];
        $totalEarnedScore = 0.0;

        foreach ($challenges as $challenge) {
            if (isset($existingDraftAnswers[$challenge->id]) && is_array($existingDraftAnswers[$challenge->id])) {
                $entry = $existingDraftAnswers[$challenge->id];
                $answersBreakdown[$challenge->id] = $entry;
                $totalEarnedScore += (float) ($entry['points_earned'] ?? 0.0);
                continue;
            }

            $rawAnswer = trim((string) ($submittedAnswers[$challenge->id] ?? ''));
            $entry = $this->evaluateSingleChallenge(
                $challenge,
                $rawAnswer,
                $document,
                $pointsMap,
                $totalQuestions,
                $pipeline,
                $highlightService
            );
            $answersBreakdown[$challenge->id] = $entry;
            $totalEarnedScore += (float) ($entry['points_earned'] ?? 0.0);
        }

        // Skor akhir dibatasi maksimal 100
        $finalScore = (int) round(min(100, $totalEarnedScore));

        // Award XP ditentukan oleh Socrates sesuai jumlah soal & level kesulitan
        $earnedXp = $scoring->calculateEarnedXp($maxRewardXp, $finalScore);

        // Simpan submission
        $submission = StudentTeacherChallengeSubmission::updateOrCreate(
            ['user_id' => $user->id, 'document_id' => $document->id],
            [
                'score' => $finalScore,
                'xp_awarded' => $earnedXp,
                'answers' => $answersBreakdown,
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );

        // Pastikan ReadingSession ada untuk mencatat aktivitas
        $session = ReadingSession::firstOrCreate(
            ['user_id' => $user->id, 'document_id' => $document->id],
            ['status' => 'in_progress', 'progress_percent' => 0, 'started_at' => now()]
        );

        // Catat ke learning_activities
        DB::table('learning_activities')->updateOrInsert(
            [
                'user_id' => $user->id,
                'reading_session_id' => $session->id,
                'event_type' => 'teacher_challenge',
            ],
            [
                'document_id' => $document->id,
                'xp_awarded' => $earnedXp,
                'summary' => "Menyelesaikan Tantangan Guru: {$document->title} (Skor {$finalScore}/100)",
                'activity_date' => now()->toDateString(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'completed' => true,
                'score' => $finalScore,
                'xp_awarded' => $earnedXp,
                'answers_breakdown' => $answersBreakdown,
            ]);
        }

        return redirect()->route('student.teacher-challenges.show', ['document' => $document->id])
            ->with('status', "Tantangan guru berhasil dikumpulkan! Anda memperoleh skor {$finalScore}/100 (+{$earnedXp} XP).");
    }

    /**
     * Mengevaluasi satu soal Tantangan Guru (Pilihan Ganda atau Esai) agar konsisten
     * baik saat cek per-nomor interaktif maupun saat pengumpulan akhir.
     *
     * @param array<int, float> $pointsMap
     * @return array<string, mixed>
     */
    private function evaluateSingleChallenge(
        TeacherChallenge $challenge,
        string $rawAnswer,
        Document $document,
        array $pointsMap,
        int $totalQuestions,
        AnswerEvaluationPipeline $pipeline,
        AnswerHighlightService $highlightService
    ): array {
        $maxPointsForQuestion = (float) ($pointsMap[$challenge->id] ?? round(100 / max(1, $totalQuestions), 2));
        $difficulty = $challenge->difficulty ?? 'medium';

        if ($challenge->type === 'multiple_choice') {
            $chosen = strtoupper($rawAnswer);
            $correct = strtoupper((string) $challenge->correct_answer);
            $isCorrect = ($chosen !== '' && $chosen === $correct);
            $earnedPoints = $isCorrect ? $maxPointsForQuestion : 0.0;

            $correctOptionText = $challenge->options[$correct] ?? $correct;
            $chosenOptionText = $chosen ? ($challenge->options[$chosen] ?? $chosen) : '(Tidak dijawab)';

            $isBlankMcq = $chosen === '';

            return [
                'type' => 'multiple_choice',
                'difficulty' => $difficulty,
                'question' => $challenge->question,
                'context' => $challenge->context,
                'chosen' => $chosen,
                'chosen_text' => $chosenOptionText,
                'correct_answer' => $correct,
                'correct_text' => $correctOptionText,
                'is_correct' => $isCorrect,
                'evaluation_status' => $isCorrect ? 'benar' : ($isBlankMcq ? 'kosong' : 'salah'),
                'points_earned' => $earnedPoints,
                'points_max' => $maxPointsForQuestion,
                'feedback' => $isCorrect
                    ? 'Jawaban Anda tepat! Pilihan (' . $correct . ') adalah jawaban yang benar.'
                    : ($isBlankMcq
                        ? 'Anda belum memilih jawaban pada soal ini (0 poin). Berikut kunci jawaban yang benar untuk dipelajari: (' . $correct . ') ' . $correctOptionText . '.'
                        : 'Jawaban salah. Anda memilih (' . $chosen . '), sedangkan kunci jawaban yang benar adalah (' . $correct . '): ' . $correctOptionText . '.'),
            ];
        }

        $modelData = $this->resolveModelAnswer($challenge, $document);
        $mockQuestion = new SocraticQuestion([
            'document_id' => $document->id,
            'skill' => 'Analisis Esai Guru',
            'prompt' => $challenge->question,
            'context' => $challenge->context ?: ($modelData['reference_excerpt'] ?? ''),
            'hint' => $modelData['model_answer'] ?: ($challenge->rubric ?: 'Jelaskan dengan pemahaman konsep dan bukti teks bacaan.'),
        ]);

        if ($rawAnswer === '') {
            return [
                'type' => 'essay',
                'difficulty' => $difficulty,
                'question' => $challenge->question,
                'context' => $challenge->context,
                'student_answer' => '(Tidak diisi)',
                'highlighted_answer_html' => e('(Tidak diisi)'),
                'points_earned' => 0.0,
                'points_max' => $maxPointsForQuestion,
                'quality_percent' => 0,
                'understanding_level' => 'Jawaban Kosong',
                'evaluation_status' => 'kosong',
                'has_evidence' => false,
                'is_copied' => false,
                'feedback' => 'Anda belum mengisi jawaban pada soal ini (0 poin). Berikut kunci jawaban yang benar berdasarkan materi untuk dipelajari.',
                'model_answer' => $modelData['model_answer'],
                'reference_excerpt' => $modelData['reference_excerpt'],
                'missing_concepts' => [],
            ];
        }

        if (mb_strlen($rawAnswer) < 10) {
            return [
                'type' => 'essay',
                'difficulty' => $difficulty,
                'question' => $challenge->question,
                'context' => $challenge->context,
                'student_answer' => $rawAnswer,
                'highlighted_answer_html' => e($rawAnswer),
                'points_earned' => 0.0,
                'points_max' => $maxPointsForQuestion,
                'quality_percent' => 0,
                'understanding_level' => 'Salah / Belum Tepat',
                'evaluation_status' => 'salah',
                'has_evidence' => false,
                'is_copied' => false,
                'feedback' => 'Jawaban esai terlalu singkat sehingga belum dapat dinilai tepat (0 poin). Pelajari kunci jawaban yang benar di bawah ini.',
                'model_answer' => $modelData['model_answer'],
                'reference_excerpt' => $modelData['reference_excerpt'],
                'missing_concepts' => ['Penjelasan konsep materi secara utuh', 'Bukti fakta dari bacaan'],
            ];
        }

        $evalResult = $pipeline->evaluate($document, $mockQuestion, $rawAnswer);
        $qualityPercent = max(0, min(100, $evalResult->score));

        if ($qualityPercent < 40 || $evalResult->understandingLevel === 'Salah / Belum Tepat') {
            $earnedPoints = 0.0;
            $evaluationStatus = 'salah';
            $understandingLevel = 'Salah / Belum Tepat';
            $feedback = $evalResult->feedback ?: 'Jawaban belum tepat atau tidak sesuai dengan materi bacaan. Pelajari kunci jawaban yang benar di bawah ini.';
        } elseif ($qualityPercent < 70) {
            $earnedPoints = round(($qualityPercent / 100) * $maxPointsForQuestion, 2);
            $evaluationStatus = 'hampir_benar';
            $understandingLevel = 'Hampir Benar (Sebagian Benar)';
            $feedback = $evalResult->feedback ?: 'Jawaban hampir benar dan menyentuh sebagian konsep, namun belum tuntas. Pelajari penyempurnaannya di bawah.';
        } else {
            $earnedPoints = round(($qualityPercent / 100) * $maxPointsForQuestion, 2);
            $evaluationStatus = 'benar';
            $understandingLevel = $evalResult->understandingLevel ?: 'Tepat & Sesuai';
            $feedback = $evalResult->feedback ?: 'Jawaban Anda tepat, logis, dan sesuai dengan fakta materi bacaan.';
        }

        $matchedSpans = is_array($evalResult->layerDetails['matched_spans'] ?? null)
            ? $evalResult->layerDetails['matched_spans']
            : [];
        $highlightSpans = ($evaluationStatus !== 'salah' && mb_strlen($rawAnswer) >= 12)
            ? (! empty($matchedSpans) ? $matchedSpans : $highlightService->extractMatchedSpans($rawAnswer, $modelData['model_answer'] ?: $modelData['reference_excerpt'], $document))
            : [];
        $highlightedAnswerHtml = $highlightService->formatHighlightedHtml($rawAnswer, $highlightSpans, $evaluationStatus !== 'salah');

        return [
            'type' => 'essay',
            'difficulty' => $difficulty,
            'question' => $challenge->question,
            'context' => $challenge->context,
            'student_answer' => $rawAnswer,
            'highlighted_answer_html' => $highlightedAnswerHtml,
            'points_earned' => $earnedPoints,
            'points_max' => $maxPointsForQuestion,
            'quality_percent' => $qualityPercent,
            'understanding_level' => $understandingLevel,
            'evaluation_status' => $evaluationStatus,
            'has_evidence' => $evalResult->hasEvidence,
            'is_copied' => $evalResult->isCopied,
            'feedback' => $feedback,
            'model_answer' => $modelData['model_answer'],
            'reference_excerpt' => $modelData['reference_excerpt'],
            'matched_spans' => $matchedSpans,
            'missing_concepts' => $evalResult->missingConcepts,
            'hint' => $evalResult->hint,
        ];
    }

    public function seedDefaultTeacherChallenges(Document $document): void
    {
        $processor = app(\App\Services\AI\DocumentAiProcessor::class);
        $questions = $processor->generateTeacherChallengeSuggestions((string) $document->body, (string) $document->title, $document->category);

        $document->teacherChallenges()->delete();

        foreach ($questions as $index => $q) {
            $diff = $q['difficulty'] ?? 'medium';
            $document->teacherChallenges()->create([
                'sequence' => $index + 1,
                'type' => 'essay',
                'question' => $q['question'],
                'context' => $q['context'] ?? null,
                'rubric' => $q['rubric'] ?? '',
                'difficulty' => $diff,
                'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
            ]);
        }
    }

    /**
     * Menghasilkan kunci jawaban yang benar (model answer) dan kutipan materi pendukung
     * untuk soal esai tantangan guru.
     *
     * @return array{model_answer: string, reference_excerpt: string}
     */
    public function resolveModelAnswer(TeacherChallenge $challenge, Document $document): array
    {
        $documentBody = (string) $document->body;
        $questionText = (string) $challenge->question;
        $rubricText = trim((string) $challenge->rubric);
        $storedReference = trim((string) ($challenge->reference_excerpt ?? ''));

        // Stopwords umum bahasa Indonesia untuk memfilter kata fungsional
        $stopwords = [
            'apa', 'yang', 'dimaksud', 'dengan', 'jelaskan', 'mengapa', 'bagaimana', 'uraikan',
            'sebutkan', 'tuliskan', 'konsep', 'terkait', 'dalam', 'materi', 'bacaan', 'fokuslah',
            'pada', 'dan', 'atau', 'ini', 'itu', 'untuk', 'adalah', 'yaitu', 'yakni', 'sebagai',
            'teks', 'kemukakan', 'temukan', 'telaah', 'rancang', 'konteks', 'petunjuk', 'dari',
            'oleh', 'karena', 'jika', 'maka', 'akan', 'sudah', 'telah', 'sedang', 'serta',
        ];
        $stopwordMap = array_flip($stopwords);

        // Jangan masukkan teks rubrik jika rubrik adalah instruksi meta generik atau template lama
        $isMetaInstruction = (bool) preg_match('/^(?:gunakan nalar|perhatikan|fokuslah|cari bagian|analisis|pikirkan|kembangkan|telaah|rancang)/i', $rubricText);
        $isLegacyBad = (bool) preg_match('/Menjelaskan pengertian|Berdasarkan bacaan:\s*Menjelaskan/iu', $rubricText . ' ' . $storedReference);
        $rubricWords = preg_split('/\s+/u', $rubricText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $hasExplanatoryVerb = (bool) preg_match('/\b(?:adalah|merupakan|yaitu|yakni|berfungsi|berperan|bertujuan|memiliki|menekankan|untuk|karena|sehingga|memadukan|mengatur|melindungi|menjamin|mencakup|menghasilkan|memungkinkan|berarti|menjelaskan|mendukung|bekerja|mengubah|menerjemahkan|mengarahkan|menyediakan|menentukan)\b/iu', $rubricText);
        $isRichRubric = mb_strlen($rubricText) >= 35 && count($rubricWords) >= 6 && $hasExplanatoryVerb && ! $isMetaInstruction && ! $isLegacyBad;

        if ($isRichRubric && $storedReference !== '') {
            return [
                'model_answer' => rtrim($rubricText, '.') . '.',
                'reference_excerpt' => $storedReference,
            ];
        }

        $searchSource = $questionText . ((! $isMetaInstruction && ! $isLegacyBad) ? ' ' . $rubricText : '');

        $rawTokens = preg_split('/\s+/u', mb_strtolower($searchSource), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];
        foreach ($rawTokens as $t) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $t);
            if ($clean && mb_strlen($clean) >= 3 && ! isset($stopwordMap[$clean])) {
                $keywords[] = $clean;
            }
        }
        $keywords = array_values(array_unique($keywords));

        // Ekstrak baris/paragraf bacaan substantif (lewati heading, tujuan pembelajaran, daftar pustaka, dan blok kode)
        $rawLines = preg_split('/(?:\r\n|\r|\n)+/u', $documentBody) ?: [];
        $rawChunks = [];
        $inCode = false;
        foreach ($rawLines as $line) {
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, '```')) {
                $inCode = ! $inCode;
                continue;
            }
            if ($inCode || $trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }
            $plainLine = trim(preg_replace('/^[\s•\-\*\d\.\)]+/u', '', preg_replace('/[*_`]/u', '', $trimmedLine) ?? '') ?? '');
            if (mb_strlen($plainLine) < 25) {
                continue;
            }
            if (preg_match('/^(?:setelah mengikuti|tujuan pembelajaran|lembar kerja|sumber rujukan|daftar pustaka|terima kasih|referensi|menjelaskan pengertian|mengidentifikasi kedudukan|menunjukkan sikap|menyusun strategi)/iu', $plainLine)) {
                continue;
            }
            if (preg_match('/^[A-Z][\p{L}\-]+,\s*[A-Z]\..*\(\d{4}\)/u', $plainLine)) {
                continue;
            }
            $rawChunks[] = $plainLine;
        }

        $scoredChunks = [];
        foreach ($rawChunks as $cleanChunk) {
            $chunkLower = mb_strtolower($cleanChunk);
            $matches = 0;
            foreach ($keywords as $kw) {
                if (mb_stripos($chunkLower, $kw) !== false) {
                    $matches++;
                }
            }
            if ($matches > 0) {
                // Beri bobot ekstra jika kalimat mengandung penjelasan definisi/fungsi/prinsip
                $bonus = preg_match('/\b(?:adalah|merupakan|yaitu|berfungsi|bertujuan|prinsip|pasal\s+\d+)\b/iu', $cleanChunk) ? 2 : 0;
                $scoredChunks[] = [
                    'chunk' => $cleanChunk,
                    'score' => $matches + $bonus,
                ];
            }
        }

        // Urutkan berdasarkan skor kecocokan tertinggi
        usort($scoredChunks, fn ($a, $b) => $b['score'] <=> $a['score']);

        $cleanText = function (string $str): string {
            $str = preg_replace('/^[\s•\-\*\#\d\.\(\)\"\'\:\;]+/u', '', $str);
            $str = preg_replace('/[\"\'\*\_]+$/u', '', $str);
            return trim(preg_replace('/\s+/u', ' ', (string) $str));
        };

        $bestChunkClean = ! empty($scoredChunks) ? $cleanText($scoredChunks[0]['chunk']) : '';
        $secondChunkClean = (count($scoredChunks) > 1) ? $cleanText($scoredChunks[1]['chunk']) : '';

        if ($isRichRubric) {
            $modelAnswer = rtrim($rubricText, '.') . '.';
        } elseif ($bestChunkClean) {
            $modelAnswer = rtrim($bestChunkClean, '.') . '.';
            if ($secondChunkClean && mb_strlen($bestChunkClean) < 160 && $secondChunkClean !== $bestChunkClean) {
                $modelAnswer .= " Selain itu, " . rtrim($secondChunkClean, '.') . '.';
            }
        } else {
            $fallbackChunk = ! empty($rawChunks) ? $cleanText($rawChunks[0]) : '';
            $modelAnswer = $fallbackChunk ?: ('Jawaban yang benar merujuk pada konsep materi ' . $document->title . ' sebagaimana dijelaskan dalam teks bacaan.');
        }

        $referenceExcerpt = ($storedReference !== '' && ! $isLegacyBad)
            ? $storedReference
            : ($bestChunkClean ?: ($fallbackChunk ?? \Illuminate\Support\Str::limit(trim(preg_replace('/[*_#`\r\n]+/u', ' ', $document->excerpt ?: $document->body)), 220)));

        return [
            'model_answer' => $modelAnswer,
            'reference_excerpt' => $referenceExcerpt,
        ];
    }
}
