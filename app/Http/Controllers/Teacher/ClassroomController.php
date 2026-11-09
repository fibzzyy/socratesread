<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $teacherId = auth()->id();
        $status = $request->query('status', 'active');

        $query = TeacherClass::query()
            ->where('teacher_id', $teacherId)
            ->withCount(['students' => fn ($students) => $students->where('role', 'student')])
            ->latest();

        if ($status === 'archived') {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        $classrooms = $query->get();

        foreach ($classrooms as $classroom) {
            $classroom->setAttribute('active_readers_count', $classroom->students()
                ->where('role', 'student')
                ->whereHas('readingSessions', fn ($sessions) => $sessions->whereHas(
                    'document', fn ($documents) => $this->classroomDocuments($documents, $classroom, $teacherId),
                ))
                ->count());
        }

        $activeCount = TeacherClass::query()->where('teacher_id', $teacherId)->where('is_active', true)->count();
        $archivedCount = TeacherClass::query()->where('teacher_id', $teacherId)->where('is_active', false)->count();

        return view('teacher.classrooms', compact('classrooms', 'status', 'activeCount', 'archivedCount'));
    }

    public function members(TeacherClass $classroom): View
    {
        abort_unless($classroom->teacher_id === auth()->id(), 404);

        $teacherId = auth()->id();
        $students = $classroom->students()
            ->where('role', 'student')
            ->withCount(['readingSessions as teacher_reading_sessions_count' => fn ($sessions) => $sessions->whereHas(
                'document', fn ($documents) => $this->classroomDocuments($documents, $classroom, $teacherId),
            )])
            ->withAvg(['readingSessions as teacher_average_progress' => fn ($sessions) => $sessions->whereHas(
                'document', fn ($documents) => $this->classroomDocuments($documents, $classroom, $teacherId),
            )], 'progress_percent')
            ->orderBy('name')
            ->paginate(20);

        return view('teacher.classroom-members', compact('classroom', 'students'));
    }

    public function removeMember(TeacherClass $classroom, User $student): RedirectResponse
    {
        abort_unless($classroom->teacher_id === auth()->id(), 404);
        abort_unless($student->role === 'student', 404);

        $membership = $classroom->students()
            ->where('users.id', $student->id)
            ->first();
        abort_unless($membership, 404);

        $classroom->students()->detach($student->id);

        return redirect()
            ->route('teacher.classrooms.members', $classroom)
            ->with('status', "{$student->name} telah dikeluarkan dari kelas {$classroom->name}. Progres kelas akan muncul kembali jika akun yang sama bergabung lagi.");
    }

    private function classroomDocuments($documents, TeacherClass $classroom, int $teacherId)
    {
        return $documents
            ->where('uploaded_by', $teacherId)
            ->where('category', $classroom->name)
            ->when($classroom->target_grade, fn ($query) => $query->whereIn('target_grade', [
                'all', (string) $classroom->target_grade,
            ]));
    }

    public function toggleStatus(Request $request, TeacherClass $classroom): RedirectResponse
    {
        if ($classroom->teacher_id !== auth()->id()) {
            abort(403);
        }

        $classroom->update([
            'is_active' => ! $classroom->is_active,
        ]);

        $message = $classroom->is_active
            ? "Kelas {$classroom->name} berhasil diaktifkan kembali."
            : "Kelas {$classroom->name} berhasil diarsipkan.";

        return back()->with('status', $message);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_grade' => ['nullable', 'integer', 'in:10,11,12'],
        ]);

        do {
            $joinCode = Str::upper(Str::random(8));
        } while (TeacherClass::query()->where('join_code', $joinCode)->exists());

        auth()->user()->teacherClasses()->create([
            'name' => $validated['name'],
            'target_grade' => $validated['target_grade'] ?? null,
            'join_code' => $joinCode,
        ]);

        return redirect()->route('teacher.classrooms')->with('status', 'Kelas berhasil dibuat. Bagikan kode gabung kepada siswa.');
    }
}
