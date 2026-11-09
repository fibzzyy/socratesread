<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherChallenge;
use App\Services\Evaluation\AnswerEvaluationPipeline;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentTeacherChallengeController extends Controller
{
    public function show(Document $document): View|RedirectResponse
    {
        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();

        if ($challenges->isEmpty()) {
            $this->seedDefaultTeacherChallenges($document);
            $challenges = $document->teacherChallenges()->orderBy('sequence')->get();
        }

        $user = auth()->user();
        $submission = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        $totalQuestions = $challenges->count();
        $pointsPerQuestion = $totalQuestions > 0 ? round(100 / $totalQuestions, 2) : 0;

        return view('student.teacher-challenge', compact(
            'document',
            'challenges',
            'submission',
            'totalQuestions',
            'pointsPerQuestion'
        ));
    }

    public function submit(
        Request $request,
        Document $document,
        AnswerEvaluationPipeline $pipeline,
        GamificationService $gamification
    ): JsonResponse|RedirectResponse {
        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();

        if ($challenges->isEmpty()) {
            $this->seedDefaultTeacherChallenges($document);
            $challenges = $document->teacherChallenges()->orderBy('sequence')->get();
        }

        $user = $request->user();
        $submission = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        if ($submission && $submission->isCompleted()) {
            return response()->json(['message' => 'Tantangan guru ini sudah selesai dikumpulkan.'], 403);
        }

        $submittedAnswers = $request->input('answers', []);
        $totalQuestions = $challenges->count();
        $pointsPerQuestion = round(100 / $totalQuestions, 2);

        $answersBreakdown = [];
        $totalEarnedScore = 0.0;

        foreach ($challenges as $challenge) {
            $rawAnswer = trim((string) ($submittedAnswers[$challenge->id] ?? ''));

            if ($challenge->type === 'multiple_choice') {
                $chosen = strtoupper($rawAnswer);
                $correct = strtoupper((string) $challenge->correct_answer);
                $isCorrect = ($chosen !== '' && $chosen === $correct);
                $earnedPoints = $isCorrect ? $pointsPerQuestion : 0.0;

                $totalEarnedScore += $earnedPoints;

                $correctOptionText = $challenge->options[$correct] ?? $correct;
                $chosenOptionText = $chosen ? ($challenge->options[$chosen] ?? $chosen) : '(Tidak dijawab)';

                $answersBreakdown[$challenge->id] = [
                    'type' => 'multiple_choice',
                    'question' => $challenge->question,
                    'chosen' => $chosen,
                    'chosen_text' => $chosenOptionText,
                    'correct_answer' => $correct,
                    'correct_text' => $correctOptionText,
                    'is_correct' => $isCorrect,
                    'evaluation_status' => $isCorrect ? 'benar' : 'salah',
                    'points_earned' => $earnedPoints,
                    'points_max' => $pointsPerQuestion,
                    'feedback' => $isCorrect
                        ? 'Jawaban Anda tepat! Pilihan (' . $correct . ') adalah jawaban yang benar.'
                        : 'Jawaban salah. Anda memilih (' . ($chosen ?: 'Kosong') . '), sedangkan kunci jawaban yang benar adalah (' . $correct . '): ' . $correctOptionText . '.',
                ];
            } else {
                // Tipe Esai: Evaluasi menggunakan pipeline analisis hybrid
                $mockQuestion = new SocraticQuestion([
                    'document_id' => $document->id,
                    'skill' => 'Analisis Esai Guru',
                    'prompt' => $challenge->question,
                    'hint' => $challenge->rubric ?: 'Jelaskan dengan pemahaman konsep dan bukti teks bacaan.',
                ]);

                $modelData = $this->resolveModelAnswer($challenge, $document);

                if (empty($rawAnswer) || mb_strlen($rawAnswer) < 10) {
                    $earnedPoints = 0.0;
                    $answersBreakdown[$challenge->id] = [
                        'type' => 'essay',
                        'question' => $challenge->question,
                        'student_answer' => $rawAnswer ?: '(Tidak diisi)',
                        'points_earned' => 0.0,
                        'points_max' => $pointsPerQuestion,
                        'quality_percent' => 0,
                        'understanding_level' => 'Salah / Belum Tepat',
                        'evaluation_status' => 'salah',
                        'feedback' => 'Jawaban esai belum diisi atau terlalu singkat sehingga dinyatakan salah (0 poin). Pelajari kunci jawaban yang benar di bawah ini.',
                        'model_answer' => $modelData['model_answer'],
                        'reference_excerpt' => $modelData['reference_excerpt'],
                        'missing_concepts' => ['Penjelasan konsep materi secara utuh', 'Bukti fakta dari bacaan'],
                    ];
                } else {
                    $evalResult = $pipeline->evaluate($document, $mockQuestion, $rawAnswer);
                    $qualityPercent = max(0, min(100, $evalResult->score));

                    // Ketentuan tegas user: "kalau jawabannya salah maka salahkan"
                    // Jika kualitas < 40% atau level Salah / Belum Tepat:
                    if ($qualityPercent < 40 || $evalResult->understandingLevel === 'Salah / Belum Tepat') {
                        $earnedPoints = 0.0;
                        $evaluationStatus = 'salah';
                        $understandingLevel = 'Salah / Belum Tepat';
                        $feedback = $evalResult->feedback ?: 'Jawaban belum tepat atau tidak sesuai dengan materi bacaan. Pelajari kunci jawaban yang benar di bawah ini.';
                    } elseif ($qualityPercent < 70) {
                        $earnedPoints = round(($qualityPercent / 100) * $pointsPerQuestion, 2);
                        $evaluationStatus = 'hampir_benar';
                        $understandingLevel = 'Hampir Benar (Sebagian Benar)';
                        $feedback = $evalResult->feedback ?: 'Jawaban hampir benar dan menyentuh sebagian konsep, namun belum tuntas. Pelajari penyempurnaannya di bawah.';
                    } else {
                        $earnedPoints = round(($qualityPercent / 100) * $pointsPerQuestion, 2);
                        $evaluationStatus = 'benar';
                        $understandingLevel = $evalResult->understandingLevel ?: 'Tepat & Sesuai';
                        $feedback = $evalResult->feedback ?: 'Jawaban Anda tepat, logis, dan sesuai dengan fakta materi bacaan.';
                    }

                    $totalEarnedScore += $earnedPoints;

                    $answersBreakdown[$challenge->id] = [
                        'type' => 'essay',
                        'question' => $challenge->question,
                        'student_answer' => $rawAnswer,
                        'points_earned' => $earnedPoints,
                        'points_max' => $pointsPerQuestion,
                        'quality_percent' => $qualityPercent,
                        'understanding_level' => $understandingLevel,
                        'evaluation_status' => $evaluationStatus,
                        'feedback' => $feedback,
                        'model_answer' => $modelData['model_answer'],
                        'reference_excerpt' => $modelData['reference_excerpt'],
                        'missing_concepts' => $evalResult->missingConcepts,
                        'hint' => $evalResult->hint,
                    ];
                }
            }
        }

        // Skor akhir dibatasi maksimal 100
        $finalScore = (int) round(min(100, $totalEarnedScore));

        // Award XP berdasarkan skor
        $earnedXp = match (true) {
            $finalScore === 100 => 35,
            $finalScore >= 80 => 30,
            $finalScore >= 60 => 20,
            $finalScore > 0 => 10,
            default => 5,
        };

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
            ['status' => 'completed', 'progress_percent' => 100, 'started_at' => now()]
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

    public function seedDefaultTeacherChallenges(Document $document): void
    {
        $processor = app(\App\Services\AI\DocumentAiProcessor::class);
        $questions = $processor->generateHeuristicSocraticQuestions($document->body, $document->title, $document->category);

        $document->teacherChallenges()->delete();

        foreach ($questions as $index => $q) {
            $document->teacherChallenges()->create([
                'sequence' => $index + 1,
                'type' => 'essay',
                'question' => $q['prompt'] . (! empty($q['context']) ? " (Konteks: {$q['context']})" : ''),
                'rubric' => $q['hint'],
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

        // Stopwords umum bahasa Indonesia untuk memfilter kata fungsional
        $stopwords = [
            'apa', 'yang', 'dimaksud', 'dengan', 'jelaskan', 'mengapa', 'bagaimana', 'uraikan',
            'sebutkan', 'tuliskan', 'konsep', 'terkait', 'dalam', 'materi', 'bacaan', 'fokuslah',
            'pada', 'dan', 'atau', 'ini', 'itu', 'untuk', 'adalah', 'yaitu', 'yakni', 'sebagai',
            'teks', 'kemukakan', 'temukan', 'telaah', 'rancang', 'konteks', 'petunjuk', 'dari',
            'oleh', 'karena', 'jika', 'maka', 'akan', 'sudah', 'telah', 'sedang', 'serta',
        ];
        $stopwordMap = array_flip($stopwords);

        // Jangan masukkan teks rubrik jika rubrik adalah instruksi meta generik
        $isMetaInstruction = (bool) preg_match('/^(?:gunakan nalar|perhatikan|fokuslah|cari bagian|analisis|pikirkan|kembangkan|telaah|rancang)/i', $rubricText);
        $searchSource = $questionText . ($isMetaInstruction ? '' : ' ' . $rubricText);

        $rawTokens = preg_split('/\s+/u', mb_strtolower($searchSource), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];
        foreach ($rawTokens as $t) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $t);
            if ($clean && mb_strlen($clean) >= 3 && ! isset($stopwordMap[$clean])) {
                $keywords[] = $clean;
            }
        }
        $keywords = array_values(array_unique($keywords));

        // Ekstrak baris/paragraf bacaan
        $rawChunks = array_filter(
            preg_split('/(?:\r\n|\r|\n)+/u', $documentBody) ?: [],
            fn ($c) => mb_strlen(trim($c)) >= 20
        );

        $scoredChunks = [];
        foreach ($rawChunks as $chunk) {
            $cleanChunk = trim($chunk);
            if (preg_match('/^(?:tujuan pembelajaran|sumber rujukan|daftar pustaka|terima kasih|referensi)/i', $cleanChunk)) {
                continue;
            }
            $chunkLower = mb_strtolower($cleanChunk);
            $matches = 0;
            foreach ($keywords as $kw) {
                if (mb_stripos($chunkLower, $kw) !== false) {
                    $matches++;
                }
            }
            if ($matches > 0) {
                $scoredChunks[] = [
                    'chunk' => $cleanChunk,
                    'score' => $matches,
                ];
            }
        }

        // Urutkan berdasarkan skor kecocokan tertinggi
        usort($scoredChunks, fn ($a, $b) => $b['score'] <=> $a['score']);

        $cleanText = function (string $str): string {
            $str = preg_replace('/^[\s•\-\*\#\d\.\(\)\"\'\:\;]+/u', '', $str);
            $str = preg_replace('/[\"\'\*\_]+$/u', '', $str);
            $str = preg_replace('/([a-z])([A-Z])/u', '$1 $2', (string) $str);
            return trim(preg_replace('/\s+/u', ' ', (string) $str));
        };

        $bestChunkClean = ! empty($scoredChunks) ? $cleanText($scoredChunks[0]['chunk']) : '';
        $secondChunkClean = (count($scoredChunks) > 1) ? $cleanText($scoredChunks[1]['chunk']) : '';

        // Tentukan model answer:
        // Cek jika rubrik memuat kunci jawaban deskriptif yang eksplisit dan BUKAN instruksi meta
        $isRichRubric = mb_strlen($rubricText) > 25 && ! $isMetaInstruction;

        if ($isRichRubric) {
            $modelAnswer = $rubricText;
            if ($bestChunkClean && ! str_contains(mb_strtolower($rubricText), mb_strtolower(mb_substr($bestChunkClean, 0, 30)))) {
                $modelAnswer .= ". Berdasarkan bacaan: " . $bestChunkClean;
            }
        } elseif ($bestChunkClean) {
            $modelAnswer = $bestChunkClean;
            if ($secondChunkClean && mb_strlen($bestChunkClean) < 140 && $secondChunkClean !== $bestChunkClean) {
                $modelAnswer .= ". Selain itu, " . lcfirst($secondChunkClean);
            }
        } else {
            // Ambil baris bermakna pertama yang substantif dari teks materi
            $meaningful = array_values(array_filter($rawChunks, fn ($c) => ! preg_match('/^(?:tujuan|lembar|daftar|sumber|terima)/i', trim($c))));
            $fallbackChunk = ! empty($meaningful) ? $cleanText($meaningful[0]) : '';
            $modelAnswer = $fallbackChunk ?: ('Jawaban yang benar merujuk pada konsep materi ' . $document->title . ' sebagaimana dijelaskan dalam teks bacaan.');
        }

        $referenceExcerpt = $bestChunkClean ?: ($fallbackChunk ?? \Illuminate\Support\Str::limit(trim(preg_replace('/[*_#`\r\n]+/u', ' ', $document->excerpt ?: $document->body)), 220));

        return [
            'model_answer' => $modelAnswer,
            'reference_excerpt' => $referenceExcerpt,
        ];
    }
}
