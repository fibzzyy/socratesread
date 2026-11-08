<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Exceptions\PdfDocumentRejected;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\TeacherClass;
use App\Services\AI\DocumentAiProcessor;
use App\Services\PdfTextExtractor;
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
        try {
            $extracted = $extractor->extract($request->file('file'));
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
            $title = trim($request->string('title')->toString());
            $category = trim($request->string('category')->toString());

            $processed = $aiProcessor->process($extracted['text'], $title, $category);

            DB::transaction(function () use ($request, $title, $category, $processed, $filePath): void {
                preg_match_all('/[\p{L}\p{N}]+/u', $processed['cleaned_body'], $words);
                $wordCount = count($words[0]);
                $estimatedMinutes = min(60, max(1, (int) ceil($wordCount / 200)));
                $quizMinutes = max(1, min(180, (int) ($request->input('quiz_duration_minutes') ?: 15)));
                $quizCount = max(5, min(100, (int) ($request->input('quiz_question_count') ?: 10)));

                $document = Document::query()->create([
                    'title' => $title,
                    'category' => $category,
                    'target_grade' => $request->input('target_grade') ?: 'all',
                    'excerpt' => $processed['excerpt'],
                    'body' => $processed['cleaned_body'],
                    'source_file_path' => $filePath,
                    'uploaded_by' => $request->user()->id,
                    'estimated_minutes' => $estimatedMinutes,
                    'quiz_duration_minutes' => $quizMinutes,
                    'quiz_question_count' => $quizCount,
                    'is_published' => true,
                ]);

                foreach ($processed['questions'] as $q) {
                    $document->questions()->create([
                        'sequence' => $q['sequence'],
                        'skill' => $q['skill'],
                        'prompt' => $q['prompt'],
                        'context' => $q['context'],
                        'hint' => $q['hint'],
                    ]);

                    // Seed starting teacher essay challenge
                    $document->teacherChallenges()->create([
                        'sequence' => $q['sequence'],
                        'type' => 'essay',
                        'question' => $q['prompt'] . (! empty($q['context']) ? " (Konteks: {$q['context']})" : ''),
                        'rubric' => ! empty($q['hint']) ? $q['hint'] : 'Analisis pemahaman mendalam, bukti dari teks, dan penalaran logis.',
                    ]);
                }

                if (! empty($processed['quiz_questions'])) {
                    foreach ($processed['quiz_questions'] as $qq) {
                        $document->quizQuestions()->create([
                            'sequence' => $qq['sequence'],
                            'question' => $qq['question'],
                            'options' => $qq['options'],
                            'correct_answer' => $qq['correct_answer'],
                            'explanation' => $qq['explanation'] ?? null,
                        ]);
                    }
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($filePath);
            Log::error('PDF document could not be saved.', [
                'exception' => class_basename($exception),
                'user_id' => $request->user()->id,
            ]);

            return back()->withErrors(['file' => 'Dokumen belum berhasil disimpan. Silakan coba lagi.'])->withInput();
        }

        return redirect()->route('teacher.documents')->with('status', 'PDF berhasil diproses dengan AI: teks telah dirapikan, 10 soal Pilihan Ganda (Kuis Timer & Latihan) serta 5 pertanyaan Esai Sokratik telah dibuat otomatis.');
    }

    public function updateQuizDuration(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        abort_unless($document->uploaded_by === auth()->id(), 403);

        $validated = $request->validate([
            'quiz_duration_minutes' => ['nullable', 'integer', 'between:1,180'],
            'quiz_question_count' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $updates = [];
        if (isset($validated['quiz_duration_minutes'])) {
            $updates['quiz_duration_minutes'] = $validated['quiz_duration_minutes'];
        }
        if (isset($validated['quiz_question_count'])) {
            $targetCount = (int) $validated['quiz_question_count'];
            $updates['quiz_question_count'] = $targetCount;

            // Pastikan bank soal kuis memiliki setidaknya jumlah soal yang dipilih guru
            if ($document->quizQuestions()->count() < $targetCount) {
                $processor = app(\App\Services\AI\DocumentAiProcessor::class);
                $generated = $processor->generateHeuristicQuizQuestions(
                    $document->body,
                    $document->title,
                    $document->category,
                    $targetCount
                );
                foreach ($generated as $qz) {
                    $document->quizQuestions()->updateOrCreate(
                        ['sequence' => $qz['sequence']],
                        [
                            'question' => $qz['question'],
                            'options' => $qz['options'],
                            'correct_answer' => $qz['correct_answer'],
                            'explanation' => $qz['explanation'] ?? null,
                        ]
                    );
                }
            }
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
