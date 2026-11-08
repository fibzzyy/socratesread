<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TeacherClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('student.classrooms', [
            'classrooms' => $classrooms,
            'user' => $student,
        ]);
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
}
