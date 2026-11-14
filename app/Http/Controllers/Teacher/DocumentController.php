<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Exceptions\PdfDocumentRejected;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\TeacherClass;
use App\Services\AI\DocumentAiProcessor;
use App\Services\PdfTextExtractor;
use App\Services\ReadingMaterialFormatter;
use App\Services\TeacherChallengeScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $selectedGrade = $request->query('grade', 'all');
        $teacherId = auth()->id();

        $query = Document::query()
            ->where('uploaded_by', $teacherId)
            ->latest();

        if (in_array($selectedGrade, ['10', '11', '12'], true)) {
            $query->where('target_grade', $selectedGrade);
        }

        $documents = $query->with(['questions', 'quizQuestions', 'teacherChallenges'])
            ->withCount(['questions', 'quizQuestions', 'teacherChallenges'])
            ->limit(50)
            ->get(['id', 'title', 'category', 'target_grade', 'excerpt', 'body', 'quiz_duration_minutes', 'quiz_question_count', 'is_published', 'created_at']);

        $formatter = app(ReadingMaterialFormatter::class);
        $scoring = app(TeacherChallengeScoringService::class);
        $documents->each(function (Document $doc) use ($formatter, $scoring): void {
            $doc->setAttribute('formatted_body_html', $formatter->toHtml((string) $doc->body));
            $pointsMap = $scoring->distributePoints($doc->teacherChallenges);
            $doc->setAttribute('challenge_max_xp', $scoring->calculateMaxRewardXp($doc->teacherChallenges));
            $doc->teacherChallenges->each(function ($tc) use ($pointsMap): void {
                $tc->setAttribute('points_max', $pointsMap[$tc->id] ?? 0);
            });
        });

        $gradeCounts = [
            'all' => Document::query()->where('uploaded_by', $teacherId)->count(),
            '10' => Document::query()->where('uploaded_by', $teacherId)->where('target_grade', '10')->count(),
            '11' => Document::query()->where('uploaded_by', $teacherId)->where('target_grade', '11')->count(),
            '12' => Document::query()->where('uploaded_by', $teacherId)->where('target_grade', '12')->count(),
        ];

        $teacherClasses = TeacherClass::query()
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('teacher.documents', compact('documents', 'selectedGrade', 'gradeCounts', 'teacherClasses'));
    }

    public function store(
        StoreDocumentRequest $request,
        PdfTextExtractor $extractor,
        DocumentAiProcessor $aiProcessor
    ): RedirectResponse
    {
        $pageStart = null;
        $pageEnd = null;
        if ($request->input('page_scope') === 'range') {
            $pageStart = $request->filled('page_start') ? (int) $request->input('page_start') : null;
            $pageEnd = $request->filled('page_end') ? (int) $request->input('page_end') : null;
        }
        $startHeading = $request->filled('start_subtopic_heading') ? trim($request->string('start_subtopic_heading')->toString()) : null;
        $lastHeading = $request->filled('last_subtopic_heading') ? trim($request->string('last_subtopic_heading')->toString()) : null;
        $title = trim($request->string('title')->toString());

        try {
            $extracted = $extractor->extract($request->file('file'), $pageStart, $pageEnd, $lastHeading, $startHeading, $title);
        } catch (Throwable $exception) {
            Log::warning('PDF document extraction failed.', [
                'exception' => class_basename($exception),
                'user_id' => $request->user()->id,
            ]);

            return back()->withErrors([
                'file' => $exception instanceof PdfDocumentRejected
                    ? $exception->getMessage()
                    : 'PDF tidak dapat dibaca. Pastikan file tidak rusak atau terkunci.',
            ])->withInput();
        }

        $filePath = $request->file('file')->store('documents', 'local');

        if (! is_string($filePath)) {
            return back()->withErrors(['file' => 'PDF belum berhasil disimpan. Silakan coba lagi.'])->withInput();
        }

        try {
            @set_time_limit(300);
            $title = trim($request->string('title')->toString());
            $category = trim($request->string('category')->toString());

            $sourceText = $request->filled('curated_body')
                ? trim($request->string('curated_body')->toString())
                : $extracted['text'];

            $processed = $aiProcessor->process($sourceText, $title, $category);
            $enableTeacherChallenges = $request->has('has_teacher_challenges_toggle')
                ? $request->boolean('enable_teacher_challenges')
                : ($request->has('enable_teacher_challenges') ? $request->boolean('enable_teacher_challenges') : true);

            $cleanedBody = ! empty($processed['cleaned_body']) ? (string) $processed['cleaned_body'] : $sourceText;
            $excerpt = ! empty($processed['excerpt']) ? (string) $processed['excerpt'] : mb_substr(strip_tags($cleanedBody), 0, 200);

            $questions = ! empty($processed['questions']) && is_array($processed['questions'])
                ? $processed['questions']
                : $aiProcessor->generateHeuristicSocraticQuestions($cleanedBody, $title, $category);

            $quizQuestions = ! empty($processed['quiz_questions']) && is_array($processed['quiz_questions'])
                ? $processed['quiz_questions']
                : $aiProcessor->generateHeuristicQuizQuestions($cleanedBody, $title, $category, 10);

            DB::transaction(function () use (
                $request,
                $title,
                $category,
                $cleanedBody,
                $excerpt,
                $questions,
                $quizQuestions,
                $processed,
                $filePath,
                $enableTeacherChallenges,
                $aiProcessor
            ): void {
                $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $cleanedBody, $words) ? count($words[0]) : 1;
                $estimatedMinutes = min(60, max(1, (int) ceil($wordCount / 200)));
                $quizMinutes = max(1, min(180, (int) ($request->input('quiz_duration_minutes') ?: 15)));
                $quizCount = max(5, min(50, (int) ($request->input('quiz_question_count') ?: 10)));

                $document = Document::query()->create([
                    'title' => $title,
                    'category' => $category,
                    'target_grade' => $request->input('target_grade') ?: 'all',
                    'excerpt' => $excerpt,
                    'body' => $cleanedBody,
                    'source_file_path' => $filePath,
                    'uploaded_by' => $request->user()->id,
                    'estimated_minutes' => $estimatedMinutes,
                    'quiz_duration_minutes' => $quizMinutes,
                    'quiz_question_count' => $quizCount,
                    'is_published' => true,
                ]);

                foreach ($questions as $idx => $q) {
                    $document->questions()->create([
                        'sequence' => $q['sequence'] ?? ($idx + 1),
                        'skill' => ! empty($q['skill']) ? (string) $q['skill'] : 'Pemahaman Konsep',
                        'prompt' => ! empty($q['prompt']) ? (string) $q['prompt'] : "Jelaskan konsep utama materi \"{$title}\"!",
                        'context' => $q['context'] ?? null,
                        'hint' => ! empty($q['hint']) ? (string) $q['hint'] : 'Uraikan jawaban berdasarkan teks materi bacaan.',
                    ]);
                }

                if ($enableTeacherChallenges) {
                    $teacherChallengeItems = ! empty($processed['teacher_challenges']) && is_array($processed['teacher_challenges'])
                        ? $processed['teacher_challenges']
                        : $aiProcessor->generateTeacherChallengeSuggestions($cleanedBody, $title, $category);

                    foreach ($teacherChallengeItems as $tcIdx => $tc) {
                        $difficulty = $tc['difficulty'] ?? 'medium';
                        $document->teacherChallenges()->create([
                            'sequence' => $tcIdx + 1,
                            'type' => $tc['type'] ?? 'essay',
                            'question' => ! empty($tc['question']) ? (string) $tc['question'] : "Analisis gagasan kritis pada materi \"{$title}\"!",
                            'context' => null,
                            'rubric' => ! empty($tc['rubric']) ? (string) $tc['rubric'] : 'Analisis pemahaman mendalam, bukti dari teks, dan penalaran logis.',
                            'reference_excerpt' => ! empty($tc['reference_excerpt']) ? (string) $tc['reference_excerpt'] : null,
                            'difficulty' => $difficulty,
                            'difficulty_weight' => $tc['difficulty_weight'] ?? \App\Models\TeacherChallenge::weightForDifficulty($difficulty),
                        ]);
                    }
                }

                foreach ($quizQuestions as $idx => $qq) {
                    $options = is_array($qq['options'] ?? null) && count($qq['options']) >= 2 ? $qq['options'] : [
                        ['key' => 'A', 'text' => 'Pernyataan benar sesuai konsep materi'],
                        ['key' => 'B', 'text' => 'Pernyataan pelengkap konteks pembahasan'],
                        ['key' => 'C', 'text' => 'Pernyataan kontras dari simpulan materi'],
                        ['key' => 'D', 'text' => 'Pernyataan yang bertentangan dengan materi'],
                    ];
                    $document->quizQuestions()->create([
                        'sequence' => $qq['sequence'] ?? ($idx + 1),
                        'question' => ! empty($qq['question']) ? (string) $qq['question'] : "Pertanyaan pemahaman konsep materi \"{$title}\"",
                        'options' => $options,
                        'correct_answer' => ! empty($qq['correct_answer']) ? (string) $qq['correct_answer'] : 'A',
                        'explanation' => ! empty($qq['explanation']) ? (string) $qq['explanation'] : 'Pembahasan berdasarkan fakta dan konsep utama materi bacaan.',
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($filePath);
            Log::error('PDF document could not be saved.', [
                'exception' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => $request->user()->id,
            ]);

            return back()->withErrors(['file' => 'Dokumen belum berhasil disimpan. Silakan coba lagi.'])->withInput();
        }

        $statusMsg = $enableTeacherChallenges
            ? 'PDF berhasil diproses dengan AI: materi pertemuan telah dirapikan, 10 butir Kuis Socrates serta 5 soal Tantangan Guru telah disiapkan.'
            : 'PDF berhasil diproses dengan AI: materi pertemuan telah dirapikan dan 10 butir Kuis Socrates telah disiapkan (Tantangan Guru dinonaktifkan).';

        return redirect()->route('teacher.documents')->with('status', $statusMsg);
    }

    public function updateQuizDuration(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $validated = $request->validate([
            'quiz_duration_minutes' => ['nullable', 'integer', 'between:1,180'],
            'quiz_question_count' => ['nullable', 'integer', 'between:5,50'],
        ]);

        $updates = [];
        if (isset($validated['quiz_duration_minutes'])) {
            $updates['quiz_duration_minutes'] = $validated['quiz_duration_minutes'];
        }
        if (isset($validated['quiz_question_count'])) {
            $targetCount = (int) $validated['quiz_question_count'];
            $updates['quiz_question_count'] = $targetCount;

            app(\App\Services\AI\DocumentAiProcessor::class)
                ->ensureQuizQuestionsForDocument($document, $targetCount);
        }

        if (! empty($updates)) {
            $document->update($updates);
        }

        if ($request->expectsJson() || $request->ajax()) {
            $count = (int) ($document->quiz_question_count ?? 10);
            return response()->json([
                'success' => true,
                'message' => "Pengaturan kuis berhasil disimpan.",
                'quiz_duration_minutes' => $document->quiz_duration_minutes,
                'quiz_question_count' => $count,
                'quiz_questions' => $document->quizQuestions()->orderBy('sequence')->take($count)->get(),
            ]);
        }

        return back()->with('status', "Pengaturan kuis Socrates untuk bacaan \"{$document->title}\" berhasil diperbarui.");
    }

    public function updateBody(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:30'],
        ]);

        $formatter = app(ReadingMaterialFormatter::class);
        $cleanedBody = $formatter->format($validated['body'], $document->title);

        $document->update([
            'body' => $cleanedBody,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Teks bacaan berhasil diperbarui.',
                'body' => $cleanedBody,
                'formatted_body_html' => $formatter->toHtml($cleanedBody),
                'chars' => mb_strlen($cleanedBody),
            ]);
        }

        return back()->with('status', "Teks bacaan \"{$document->title}\" berhasil diperbarui.");
    }

    public function destroy(Document $document): RedirectResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 404);

        $filePath = $document->source_file_path;

        DB::transaction(function () use ($document): void {
            $document->readingSessions()->delete();
            $document->questions()->delete();
            $document->delete();
        });

        if ($filePath && ! Storage::disk('local')->delete($filePath)) {
            Log::warning('Deleted document PDF could not be removed from storage.', [
                'document_id' => $document->id,
            ]);
        }

        return redirect()->route('teacher.documents')->with('status', 'Bacaan beserta sesi baca, jawaban, dan XP terkait berhasil dihapus.');
    }
}
