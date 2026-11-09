<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\StudentQuizAttempt;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $selectedCategory = $request->query('category', 'all');
        $selectedStatus = $request->query('status', 'all');

        $query = Document::query()
            ->where('is_published', true)
            ->withCount(['quizQuestions', 'teacherChallenges'])
            ->latest('id');

        if ($selectedCategory !== 'all') {
            $query->where('category', $selectedCategory);
        }

        $documents = $query->get();

        $userAttempts = StudentQuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('mode', 'timed_quiz')
            ->get()
            ->groupBy('document_id');

        $userTeacherSubmissions = \App\Models\StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('document_id');

        $categories = Document::query()
            ->where('is_published', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $quizItems = $documents->map(function ($doc) use ($userAttempts, $userTeacherSubmissions) {
            $attempts = $userAttempts->get($doc->id, collect());
            $completedAttempt = $attempts->where('status', 'completed')->sortByDesc('completed_at')->first();
            $inProgressAttempt = $attempts->where('status', 'in_progress')->sortByDesc('started_at')->first();

            $questionCount = (int) ($doc->quiz_question_count ?: min(20, max(5, $doc->quiz_questions_count ?: 10)));
            $durationMinutes = (int) ($doc->quiz_duration_minutes ?: 15);

            $status = $completedAttempt ? 'completed' : ($inProgressAttempt ? 'in_progress' : 'not_started');

            $teacherSub = $userTeacherSubmissions->get($doc->id);
            $teacherChallengesCount = $doc->teacher_challenges_count ?: $doc->teacherChallenges()->count();

            return [
                'document' => $doc,
                'question_count' => $questionCount,
                'duration_minutes' => $durationMinutes,
                'status' => $status,
                'attempt' => $completedAttempt ?: $inProgressAttempt,
                'score' => $completedAttempt?->score,
                'correct_count' => $completedAttempt?->correct_count,
                'xp_awarded' => $completedAttempt?->xp_awarded,
                'completed_at' => $completedAttempt?->completed_at,
                'teacher_challenges_count' => $teacherChallengesCount,
                'teacher_submission' => $teacherSub,
                'teacher_is_completed' => (bool) ($teacherSub && $teacherSub->isCompleted()),
                'teacher_score' => $teacherSub?->score,
                'teacher_xp' => $teacherSub?->xp_awarded,
            ];
        });

        // Global statistics before status filter
        $totalQuizzes = $quizItems->count();
        $completedCount = $quizItems->where('status', 'completed')->count();
        $inProgressCount = $quizItems->where('status', 'in_progress')->count();
        $notStartedCount = $quizItems->where('status', 'not_started')->count();
        $averageScore = $completedCount > 0 ? round($quizItems->where('status', 'completed')->avg('score'), 1) : 0;
        $totalXpEarned = $quizItems->where('status', 'completed')->sum('xp_awarded');

        if ($selectedStatus !== 'all') {
            $quizItems = $quizItems->where('status', $selectedStatus)->values();
        }

        return view('student.quizzes', compact(
            'quizItems',
            'categories',
            'selectedCategory',
            'selectedStatus',
            'totalQuizzes',
            'completedCount',
            'inProgressCount',
            'notStartedCount',
            'averageScore',
            'totalXpEarned'
        ));
    }

    public function timed(Request $request, Document|int|string|null $document = null): View|RedirectResponse
    {
        if (! $document) {
            return redirect()->route('quiz.index');
        }

        return $this->renderQuiz($request, $document, 'timed_quiz');
    }

    public function practice(Request $request, Document|int|string|null $document = null): View|RedirectResponse
    {
        if (! $document) {
            return redirect()->route('quiz.index');
        }

        return $this->renderQuiz($request, $document, 'practice');
    }

    protected function renderQuiz(Request $request, Document|int|string|null $document, string $mode): View|RedirectResponse
    {
        if (! $document instanceof Document) {
            $document = $document ? Document::query()->find($document) : null;
        }

        if (! $document) {
            $latestSession = \App\Models\ReadingSession::query()
                ->where('user_id', auth()->id())
                ->latest('started_at')
                ->first();
            if ($latestSession && $latestSession->document) {
                $document = $latestSession->document;
            }
        }

        $document ??= Document::query()->where('is_published', true)->latest('id')->first();

        if (! $document) {
            return redirect()->route('dashboard')->with('notice', 'Belum ada bacaan atau kuis yang diterbitkan.');
        }

        abort_unless($document->is_published, 404);

        $targetCount = min(100, max(5, (int) ($document->quiz_question_count ?: 10)));

        if ($document->quizQuestions()->count() < $targetCount) {
            $processor = app(\App\Services\AI\DocumentAiProcessor::class);
            $generated = $processor->generateHeuristicQuizQuestions($document->body, $document->title, $document->category, $targetCount);
            foreach ($generated as $qz) {
                $document->quizQuestions()->updateOrCreate(
                    ['sequence' => $qz['sequence']],
                    [
                        'question' => $qz['question'],
                        'options' => $qz['options'],
                        'correct_answer' => $qz['correct_answer'],
                        'explanation' => $qz['explanation'],
                    ]
                );
            }
        }

        $quizQuestions = $document->quizQuestions()->orderBy('sequence')->take($targetCount)->get();

        $userId = auth()->id();

        // 1. Check if a completed attempt exists
        $attempt = StudentQuizAttempt::query()
            ->where('user_id', $userId)
            ->where('document_id', $document->id)
            ->where('mode', $mode)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        $isLocked = false;

        if ($attempt) {
            $isLocked = true;
        } else {
            // Find or create in-progress attempt
            $attempt = StudentQuizAttempt::query()->firstOrCreate([
                'user_id' => $userId,
                'document_id' => $document->id,
                'mode' => $mode,
                'status' => 'in_progress',
            ], [
                'total_questions' => $quizQuestions->count(),
                'started_at' => now(),
            ]);
        }

        // Map questions: hide correct answer in active test mode to prevent inspection
        $questionsPayload = $quizQuestions->map(function ($q) use ($isLocked) {
            $data = [
                'id' => $q->id,
                'sequence' => $q->sequence,
                'question' => $q->question,
                'options' => $q->options,
            ];

            if ($isLocked) {
                $data['correct_answer'] = $q->correct_answer;
                $data['explanation'] = $q->explanation;
            }

            return $data;
        })->values();

        $savedAnswers = $attempt->answers ?? [];
        $quizDurationMinutes = (int) ($document->quiz_duration_minutes ?: 15);
        $quizDurationSeconds = $quizDurationMinutes * 60;

        if ($attempt->isCompleted() && ($attempt->xp_awarded ?? 0) === 0) {
            $gamification = app(GamificationService::class);
            $backfilledXp = $gamification->awardQuiz($attempt, $attempt->score);
            $attempt->update(['xp_awarded' => $backfilledXp]);
        }

        return view('student.quiz', compact('document', 'attempt', 'quizQuestions', 'questionsPayload', 'savedAnswers', 'mode', 'isLocked', 'quizDurationMinutes', 'quizDurationSeconds'));
    }

    public function submit(Request $request, GamificationService $gamification): JsonResponse
    {
        $data = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['required', 'array'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $attempt = StudentQuizAttempt::query()
            ->with('document.quizQuestions')
            ->whereKey($data['attempt_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($attempt->isCompleted()) {
            return response()->json([
                'message' => 'Kuis ini sudah selesai dikumpulkan dan terkunci.',
            ], 403);
        }

        $quizQuestions = $attempt->document->quizQuestions;
        $total = $quizQuestions->count();
        $submittedAnswers = $data['answers'];

        $correctCount = 0;
        $feedback = [];

        foreach ($quizQuestions as $q) {
            $chosen = strtoupper(trim((string) ($submittedAnswers[$q->id] ?? '')));
            $isCorrect = ($chosen !== '' && $chosen === strtoupper(trim($q->correct_answer)));

            if ($isCorrect) {
                $correctCount++;
            }

            $feedback[$q->id] = [
                'chosen' => $chosen,
                'correct_answer' => $q->correct_answer,
                'is_correct' => $isCorrect,
                'explanation' => $q->explanation,
            ];
        }

        $score = $total > 0 ? (int) round(($correctCount / $total) * 100) : 0;
        $timeSpent = (int) ($data['time_spent_seconds'] ?? 0);

        // Award XP via GamificationService
        $xpAwarded = $gamification->awardQuiz($attempt, $score);

        DB::transaction(function () use ($attempt, $score, $correctCount, $total, $submittedAnswers, $timeSpent, $xpAwarded): void {
            $attempt->update([
                'score' => $score,
                'xp_awarded' => $xpAwarded,
                'correct_count' => $correctCount,
                'total_questions' => $total,
                'answers' => $submittedAnswers,
                'status' => 'completed',
                'time_spent_seconds' => $timeSpent,
                'completed_at' => now(),
            ]);
        });

        return response()->json([
            'completed' => true,
            'score' => $score,
            'correct_count' => $correctCount,
            'total_questions' => $total,
            'xp_awarded' => $xpAwarded,
            'feedback' => $feedback,
        ]);
    }
}
