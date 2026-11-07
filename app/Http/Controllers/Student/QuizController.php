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
    public function timed(Request $request, Document|int|string|null $document = null): View|RedirectResponse
    {
        return $this->renderQuiz($request, $document, 'timed_quiz');
    }

    public function practice(Request $request, Document|int|string|null $document = null): View|RedirectResponse
    {
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

        if ($document->quizQuestions()->count() < 10) {
            $processor = app(\App\Services\AI\DocumentAiProcessor::class);
            $aiData = $processor->processWithHeuristics($document->body, $document->title, $document->category);
            foreach ($aiData['quiz_questions'] as $qz) {
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

        $quizQuestions = $document->quizQuestions()->orderBy('sequence')->get();

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

        return view('student.quiz', compact('document', 'attempt', 'quizQuestions', 'questionsPayload', 'savedAnswers', 'mode', 'isLocked'));
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

        DB::transaction(function () use ($attempt, $score, $correctCount, $total, $submittedAnswers, $timeSpent): void {
            $attempt->update([
                'score' => $score,
                'correct_count' => $correctCount,
                'total_questions' => $total,
                'answers' => $submittedAnswers,
                'status' => 'completed',
                'time_spent_seconds' => $timeSpent,
                'completed_at' => now(),
            ]);
        });

        // Award XP based on score
        $earnedXp = $score >= 70 ? 25 : ($score >= 50 ? 15 : 10);
        $summary = $attempt->mode === 'timed_quiz' ? 'Menyelesaikan Kuis Timer Pilihan Ganda' : 'Menyelesaikan Latihan Pilihan Ganda';

        // Record learning activity
        $inserted = DB::table('learning_activities')->insertOrIgnore([
            'user_id' => $attempt->user_id,
            'reading_session_id' => null,
            'document_id' => $attempt->document_id,
            'event_type' => 'quiz_' . $attempt->mode,
            'xp_awarded' => $earnedXp,
            'summary' => "{$summary} (Skor {$score}/100)",
            'activity_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $xpAwarded = $inserted === 1 ? $earnedXp : 0;

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
