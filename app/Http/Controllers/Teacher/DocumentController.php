<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Exceptions\PdfDocumentRejected;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\TeacherClass;
use App\Services\AI\DocumentAiProcessor;
use App\Services\PdfTextExtractor;
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

        $documents = $query->with(['questions', 'quizQuestions'])
            ->withCount(['questions', 'quizQuestions'])
            ->limit(50)
            ->get(['id', 'title', 'category', 'target_grade', 'excerpt', 'body', 'is_published', 'created_at']);

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

                $document = Document::query()->create([
                    'title' => $title,
                    'category' => $category,
                    'target_grade' => $request->input('target_grade') ?: 'all',
                    'excerpt' => $processed['excerpt'],
                    'body' => $processed['cleaned_body'],
                    'source_file_path' => $filePath,
                    'uploaded_by' => $request->user()->id,
                    'estimated_minutes' => $estimatedMinutes,
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
