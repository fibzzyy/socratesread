<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ReadingSession;
use App\Models\StudentQuizAttempt;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ClassroomEnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user();
        $classrooms = $student->classrooms()
            ->with('teacher')
            ->withCount('students')
            ->latest('classroom_memberships.joined_at')
            ->get();

        foreach ($classrooms as $classroom) {
            $documentIds = $this->publishedMaterials($classroom)->pluck('id')->all();
            $completedIds = $this->completedMaterialIds($student, $documentIds);
            $classroom->setAttribute('materials_count', count($documentIds));
            $classroom->setAttribute('completed_materials_count', $completedIds->count());
            $classroom->setAttribute('materials_progress', count($documentIds) > 0
                ? (int) round($completedIds->count() / count($documentIds) * 100)
                : 0);
        }

        return view('student.classrooms', [
            'classrooms' => $classrooms,
            'user' => $student,
        ]);
    }

    public function show(Request $request, TeacherClass $classroom): View
    {
        $student = $request->user();
        abort_unless($student->classrooms()->whereKey($classroom->id)->exists(), 404);

        $classroom->load('teacher');
        $documents = $this->publishedMaterials($classroom)->latest('created_at')->get();
        $documentIds = $documents->modelKeys();
        $completedIds = $this->completedMaterialIds($student, $documentIds)->flip();
        $latestSessions = ReadingSession::query()
            ->where('user_id', $student->id)
            ->whereIn('document_id', $documentIds)
            ->orderBy('started_at')
            ->get()
            ->keyBy('document_id');

        foreach ($documents as $document) {
            $document->setAttribute('student_session', $latestSessions->get($document->id));
            $document->setAttribute('is_completed', $completedIds->has($document->id));
        }

        $completedCount = $completedIds->count();
        $progress = $documents->isNotEmpty()
            ? (int) round($completedCount / $documents->count() * 100)
            : 0;

        return view('student.classroom-detail', compact('classroom', 'documents', 'completedCount', 'progress'));
    }

    public function start(Request $request, TeacherClass $classroom, Document $document): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student->classrooms()->whereKey($classroom->id)->exists(), 404);
        abort_unless($this->publishedMaterials($classroom)->whereKey($document->id)->exists(), 404);

        if ($this->completedMaterialIds($student, [$document->id])->isNotEmpty()) {
            return redirect()->route('classrooms.show', $classroom)
                ->with('notice', 'Materi ini sudah selesai. Pilih Buka untuk membaca ulang.');
        }

        $session = ReadingSession::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'document_id' => $document->id,
                'status' => 'in_progress',
            ],
            ['started_at' => now()],
        );

        return redirect()->route('challenge', ['session' => $session->id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'join_code' => ['required', 'string', 'alpha_num', 'size:8'],
        ]);

        $classroom = TeacherClass::query()
            ->where('join_code', strtoupper($validated['join_code']))
            ->where('is_active', true)
            ->first();

        if (! $classroom) {
            return back()->withErrors(['join_code' => 'Kode kelas tidak ditemukan atau sudah tidak aktif.'])->withInput();
        }

        $student = $request->user();

        if ($student->classrooms()->whereKey($classroom->id)->exists()) {
            return back()->with('notice', 'Kamu sudah tergabung di kelas ini.');
        }

        $student->classrooms()->attach($classroom->id, ['joined_at' => now()]);

        return back()->with('notice', 'Berhasil bergabung dengan kelas '.$classroom->name.'.');
    }

    private function publishedMaterials(TeacherClass $classroom): Builder
    {
        return Document::query()
            ->where('uploaded_by', $classroom->teacher_id)
            ->where('category', $classroom->name)
            ->where('is_published', true)
            ->when($classroom->target_grade, fn (Builder $query) => $query->whereIn('target_grade', [
                'all', (string) $classroom->target_grade,
            ]));
    }

    /** @param array<int, int|string> $documentIds */
    private function completedMaterialIds(User $student, array $documentIds): Collection
    {
        if ($documentIds === []) {
            return collect();
        }

        $completedChallenges = ReadingSession::query()
            ->where('user_id', $student->id)
            ->whereIn('document_id', $documentIds)
            ->where('status', 'completed')
            ->pluck('document_id');

        $completedQuizzes = StudentQuizAttempt::query()
            ->where('user_id', $student->id)
            ->whereIn('document_id', $documentIds)
            ->where('status', 'completed')
            ->pluck('document_id');

        return $completedChallenges->merge($completedQuizzes)->unique()->values();
    }
}