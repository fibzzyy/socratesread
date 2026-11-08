<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TeacherChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherChallengeController extends Controller
{
    public function index(Document $document): JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $challenges = $document->teacherChallenges()->orderBy('sequence')->get();

        if ($challenges->isEmpty()) {
            app(\App\Http\Controllers\Student\StudentTeacherChallengeController::class)->seedDefaultTeacherChallenges($document);
            $challenges = $document->teacherChallenges()->orderBy('sequence')->get();
        }

        return response()->json([
            'document_id' => $document->id,
            'title' => $document->title,
            'total_challenges' => $challenges->count(),
            'points_per_question' => $challenges->count() > 0 ? round(100 / $challenges->count(), 2) : 0,
            'challenges' => $challenges,
        ]);
    }

    public function suggestions(Document $document): JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $processor = app(\App\Services\AI\DocumentAiProcessor::class);
        $questions = $processor->generateHeuristicSocraticQuestions($document->body, $document->title, $document->category);

        $suggestions = array_map(function ($q) {
            return [
                'type' => 'essay',
                'question' => $q['prompt'] . (! empty($q['context']) ? " (Konteks: {$q['context']})" : ''),
                'rubric' => $q['hint'],
            ];
        }, $questions);

        return response()->json(['suggestions' => $suggestions]);
    }

    public function store(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $data = $request->validate([
            'challenges' => ['required', 'array', 'min:1'],
            'challenges.*.type' => ['required', 'in:essay,multiple_choice'],
            'challenges.*.question' => ['required', 'string', 'max:2000'],
            'challenges.*.options' => ['nullable', 'array'],
            'challenges.*.options.A' => ['nullable', 'string', 'max:500'],
            'challenges.*.options.B' => ['nullable', 'string', 'max:500'],
            'challenges.*.options.C' => ['nullable', 'string', 'max:500'],
            'challenges.*.options.D' => ['nullable', 'string', 'max:500'],
            'challenges.*.correct_answer' => ['nullable', 'string', 'max:10'],
            'challenges.*.rubric' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($document, $data): void {
            $document->teacherChallenges()->delete();

            foreach ($data['challenges'] as $index => $item) {
                $document->teacherChallenges()->create([
                    'sequence' => $index + 1,
                    'type' => $item['type'],
                    'question' => trim($item['question']),
                    'options' => $item['type'] === 'multiple_choice' ? ($item['options'] ?? null) : null,
                    'correct_answer' => $item['type'] === 'multiple_choice' ? strtoupper(trim($item['correct_answer'] ?? 'A')) : null,
                    'rubric' => $item['type'] === 'essay' ? trim($item['rubric'] ?? '') : null,
                ]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tantangan guru berhasil disimpan. Total skor diatur ke 100 poin.',
                'total_challenges' => count($data['challenges']),
                'points_per_question' => round(100 / count($data['challenges']), 2),
            ]);
        }

        return back()->with('status', 'Tantangan guru berhasil diperbarui. Total skor kuis/esai tetap 100 poin.');
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
