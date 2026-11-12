<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Services\AI\DocumentAiProcessor;
use App\Services\TeacherChallengeScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherChallengeController extends Controller
{
    public function index(Document $document, TeacherChallengeScoringService $scoring): JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();
        $pointsMap = $scoring->distributePoints($challenges);
        $maxXp = $scoring->calculateMaxRewardXp($challenges);

        $enriched = $challenges->map(function (TeacherChallenge $challenge) use ($pointsMap) {
            return [
                'id' => $challenge->id,
                'document_id' => $challenge->document_id,
                'type' => $challenge->type,
                'sequence' => $challenge->sequence,
                'question' => $challenge->question,
                'context' => $challenge->context,
                'options' => $challenge->options,
                'correct_answer' => $challenge->correct_answer,
                'rubric' => $challenge->rubric,
                'reference_excerpt' => $challenge->reference_excerpt,
                'difficulty' => $challenge->difficulty ?? 'medium',
                'difficulty_weight' => $challenge->difficulty_weight ?? TeacherChallenge::weightForDifficulty($challenge->difficulty),
                'points_max' => $pointsMap[$challenge->id] ?? 0,
            ];
        })->values();

        return response()->json([
            'document_id' => $document->id,
            'title' => $document->title,
            'total_challenges' => $challenges->count(),
            'points_per_question' => $challenges->count() > 0 ? round(100 / $challenges->count(), 2) : 0,
            'max_xp' => $maxXp,
            'challenges' => $enriched,
        ]);
    }

    public function suggestions(
        Document $document,
        DocumentAiProcessor $processor,
        TeacherChallengeScoringService $scoring
    ): JsonResponse {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $suggestions = $processor->generateTeacherChallengeSuggestions(
            (string) $document->body,
            (string) $document->title,
            $document->category
        );

        $pointsMap = $scoring->distributePoints($suggestions);
        $maxXp = $scoring->calculateMaxRewardXp($suggestions);

        $enriched = array_values(array_map(function (array $item, int $idx) use ($pointsMap): array {
            $item['points_max'] = $pointsMap[$idx] ?? 0;

            return $item;
        }, $suggestions, array_keys($suggestions)));

        return response()->json([
            'suggestions' => $enriched,
            'max_xp' => $maxXp,
        ]);
    }

    public function analyze(
        Request $request,
        Document $document,
        DocumentAiProcessor $processor
    ): JsonResponse {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $data = $request->validate([
            'type' => ['required', 'in:essay,multiple_choice'],
            'question' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:1000'],
            'rubric' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'string', 'max:10'],
        ]);

        $analyzed = $processor->analyzeTeacherChallenges(
            (string) $document->body,
            (string) $document->title,
            [array_merge($data, ['force_analyze' => true])]
        );

        return response()->json([
            'difficulty' => $analyzed[0]['difficulty'] ?? 'medium',
            'difficulty_weight' => $analyzed[0]['difficulty_weight'] ?? 2,
        ]);
    }

    public function recommendAnswer(
        Request $request,
        Document $document,
        DocumentAiProcessor $processor
    ): JsonResponse {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:4', 'max:2000'],
        ]);

        $recommendation = $processor->recommendAnswerForQuestion(
            (string) $document->body,
            (string) $document->title,
            $data['question'],
            $document->category
        );

        return response()->json($recommendation);
    }

    public function store(
        Request $request,
        Document $document,
        DocumentAiProcessor $processor,
        TeacherChallengeScoringService $scoring
    ): RedirectResponse|JsonResponse {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $data = $request->validate([
            'challenges' => ['nullable', 'array'],
            'challenges.*.type' => ['required', 'in:essay,multiple_choice'],
            'challenges.*.question' => ['required', 'string', 'max:2000'],
            'challenges.*.context' => ['nullable', 'string', 'max:1000'],
            'challenges.*.options' => ['nullable', 'array'],
            'challenges.*.options.A' => ['nullable', 'string', 'max:500'],
            'challenges.*.options.B' => ['nullable', 'string', 'max:500'],
            'challenges.*.options.C' => ['nullable', 'string', 'max:500'],
            'challenges.*.options.D' => ['nullable', 'string', 'max:500'],
            'challenges.*.correct_answer' => ['nullable', 'string', 'max:10'],
            'challenges.*.rubric' => ['nullable', 'string', 'max:2000'],
            'challenges.*.reference_excerpt' => ['nullable', 'string', 'max:2000'],
            'challenges.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'challenges.*.force_analyze' => ['nullable', 'boolean'],
        ]);

        $rawChallenges = $data['challenges'] ?? [];
        $analyzedChallenges = $processor->analyzeTeacherChallenges(
            (string) $document->body,
            (string) $document->title,
            $rawChallenges,
            false
        );

        $created = DB::transaction(function () use ($document, $analyzedChallenges, $processor) {
            $document->teacherChallenges()->delete();
            $savedItems = collect();

            foreach ($analyzedChallenges as $index => $item) {
                $diff = $item['difficulty'] ?? 'medium';
                $weight = (int) ($item['difficulty_weight'] ?? TeacherChallenge::weightForDifficulty($diff));
                $questionText = trim((string) $item['question']);
                $rubricText = $item['type'] === 'essay' ? trim((string) ($item['rubric'] ?? '')) : null;
                $excerptText = null;
                if ($item['type'] === 'essay') {
                    $rawExcerpt = trim((string) ($item['reference_excerpt'] ?? ''));
                    $excerptText = $rawExcerpt !== ''
                        ? $rawExcerpt
                        : ($processor->findBestExcerptForQuestion((string) $document->body, (string) $document->title, $questionText, (string) $rubricText) ?: null);
                }

                $savedItems->push($document->teacherChallenges()->create([
                    'sequence' => $index + 1,
                    'type' => $item['type'],
                    'question' => $questionText,
                    'context' => isset($item['context']) && trim((string) $item['context']) !== '' ? trim((string) $item['context']) : null,
                    'options' => $item['type'] === 'multiple_choice' ? ($item['options'] ?? null) : null,
                    'correct_answer' => $item['type'] === 'multiple_choice' ? strtoupper(trim((string) ($item['correct_answer'] ?? 'A'))) : null,
                    'rubric' => $rubricText,
                    'reference_excerpt' => $excerptText,
                    'difficulty' => $diff,
                    'difficulty_weight' => $weight,
                ]));
            }

            return $savedItems;
        });

        $count = $created->count();
        $pointsMap = $scoring->distributePoints($created);
        $maxXp = $scoring->calculateMaxRewardXp($created);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $count > 0
                    ? "Tantangan guru berhasil disimpan! Total skor proporsional 100 poin & reward maksimal +{$maxXp} XP."
                    : 'Seluruh soal tantangan guru berhasil dikosongkan.',
                'total_challenges' => $count,
                'points_per_question' => $count > 0 ? round(100 / $count, 2) : 0,
                'max_xp' => $maxXp,
                'challenges' => $created->map(fn (TeacherChallenge $tc) => [
                    'id' => $tc->id,
                    'type' => $tc->type,
                    'sequence' => $tc->sequence,
                    'question' => $tc->question,
                    'context' => $tc->context,
                    'rubric' => $tc->rubric,
                    'reference_excerpt' => $tc->reference_excerpt,
                    'options' => $tc->options,
                    'correct_answer' => $tc->correct_answer,
                    'difficulty' => $tc->difficulty,
                    'difficulty_weight' => $tc->difficulty_weight,
                    'points_max' => $pointsMap[$tc->id] ?? 0,
                ])->values(),
            ]);
        }

        return back()->with('status', 'Tantangan guru berhasil diperbarui.');
    }

    public function destroy(Document $document, TeacherChallenge $challenge): RedirectResponse|JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id() && $challenge->document_id === $document->id, 403);

        $challenge->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Soal tantangan guru berhasil dihapus.');
    }
}
