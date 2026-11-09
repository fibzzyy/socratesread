<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ReadingSession;
use App\Models\SocraticQuestion;
use App\Models\StudentAnswer;
use App\Services\Evaluation\AnswerEvaluationPipeline;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $userId = auth()->user()->id;
        $sessionId = $request->query('session');

        if ($sessionId) {
            $session = ReadingSession::query()
                ->with(['document.questions', 'answers'])
                ->whereKey($sessionId)
                ->where('user_id', $userId)
                ->first();
        } else {
            // Priority 1: Current active in_progress session
            $session = ReadingSession::query()
                ->with(['document.questions', 'answers'])
                ->where('user_id', $userId)
                ->where('status', 'in_progress')
                ->latest('started_at')
                ->first();

            // Priority 2: Latest completed session (review mode)
            if (! $session) {
                $session = ReadingSession::query()
                    ->with(['document.questions', 'answers'])
                    ->where('user_id', $userId)
                    ->where('status', 'completed')
                    ->latest('completed_at')
                    ->first();
            }
        }

        if (! $session) {
            return redirect()->route('reading')->with('notice', 'Mulai atau lanjutkan bacaan sebelum mengerjakan tantangan.');
        }

        if ($session->document->questions()->count() < 5) {
            $processor = app(\App\Services\AI\DocumentAiProcessor::class);
            $aiData = $processor->processWithHeuristics($session->document->body, $session->document->title, $session->document->category);
            foreach ($aiData['questions'] as $qData) {
                $session->document->questions()->updateOrCreate(
                    ['sequence' => $qData['sequence']],
                    [
                        'skill' => $qData['skill'],
                        'prompt' => $qData['prompt'],
                        'context' => $qData['context'],
                        'hint' => $qData['hint'],
                    ]
                );
            }
        }

        $questions = $session->document->questions()->orderBy('sequence')->get();
        if ($questions->isEmpty()) {
            return redirect()->route('reading')->with('notice', 'Pertanyaan untuk bacaan ini belum tersedia.');
        }

        $challengeQuestions = $questions->map(fn ($question) => $question->only(['id', 'skill', 'prompt', 'context', 'hint']))->values();
        $savedAnswers = $session->answers()->pluck('answer_text', 'socratic_question_id');
        $savedEvaluations = $session->answers()->get()->keyBy('socratic_question_id')->map(function ($ans) {
            return [
                'score' => (int) ($ans->score ?? 0),
                'understanding_level' => (string) ($ans->understanding_level ?? ''),
                'evaluation' => $ans->evaluation ?? [],
            ];
        });
        $isLocked = $session->isCompleted();

        return view('student.challenge', compact('session', 'questions', 'challengeQuestions', 'savedAnswers', 'savedEvaluations', 'isLocked'));
    }

    public function saveAnswer(Request $request, AnswerEvaluationPipeline $evaluator): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'question_id' => ['required', 'integer'],
            'answer' => ['required', 'string', 'max:800'],
        ]);

        $session = ReadingSession::query()
            ->with('document')
            ->whereKey($data['session_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($session->isCompleted()) {
            return response()->json([
                'message' => 'Tugas ini sudah selesai dikumpulkan dan terkunci. Jawaban tidak dapat diubah lagi.',
            ], 403);
        }

        $question = SocraticQuestion::query()
            ->whereKey($data['question_id'])
            ->where('document_id', $session->document_id)
            ->firstOrFail();

        $answerText = trim($data['answer']);

        // Jalankan evaluasi Hybrid Berlapis (Rule-Based -> Semantic Similarity -> LLM Rubrik)
        $evaluation = $evaluator->evaluate($session->document, $question, $answerText);

        StudentAnswer::query()->updateOrCreate(
            ['reading_session_id' => $session->id, 'socratic_question_id' => $question->id],
            [
                'answer_text' => $answerText,
                'score' => $evaluation->score,
                'understanding_level' => $evaluation->understandingLevel,
                'evaluation' => $evaluation->toArray(),
                'evaluated_at' => now(),
                'answered_at' => now(),
            ],
        );

        return response()->json([
            'saved' => true,
            'evaluation' => $evaluation->toArray(),
        ]);
    }

    public function complete(Request $request, GamificationService $gamification): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'integer']]);
        $session = ReadingSession::query()
            ->whereKey($data['session_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($session->isCompleted()) {
            return response()->json([
                'message' => 'Tugas ini sudah selesai dikumpulkan sebelumnya.',
            ], 403);
        }

        $questionCount = $session->document->questions()->count();
        $answerCount = $session->answers()->count();

        abort_unless($questionCount > 0 && $answerCount >= $questionCount, 422, 'Selesaikan semua jawaban terlebih dahulu.');

        $xpAwarded = DB::transaction(function () use ($session, $gamification): int {
            $session->update([
                'status' => 'completed',
                'progress_percent' => 100,
                'completed_at' => now(),
            ]);

            return $gamification->awardOnce($session, 'challenge_completed', 20, 'Menyelesaikan Socratic Challenge');
        });

        return response()->json(['completed' => true, 'xp_awarded' => $xpAwarded]);
    }
}
