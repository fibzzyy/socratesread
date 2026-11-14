<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TeacherClass;
use App\Services\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClassroomEnrollmentController extends Controller
{
    public function index(Request $request, GamificationService $gamification): View
    {
        $student = auth()->user();
        // Read the enrollment table directly so this page always reflects the same
        // membership records written by the join action.
        $classrooms = TeacherClass::query()
            ->join('classroom_memberships', 'classroom_memberships.classroom_id', '=', 'teacher_classes.id')
            ->where('classroom_memberships.student_id', $student->id)
            ->select('teacher_classes.*', 'classroom_memberships.joined_at as membership_joined_at')
            ->with('teacher:id,name')
            ->withCount('students')
            ->orderByDesc('classroom_memberships.joined_at')
            ->get();

        $assignedDocumentIds = collect();
        foreach ($classrooms as $classroom) {
            $documentIds = Document::query()
                ->where('is_published', true)
                ->where('uploaded_by', $classroom->teacher_id)
                ->where('category', $classroom->name)
                ->when($classroom->target_grade, fn ($query) => $query->whereIn('target_grade', [
                    'all', (string) $classroom->target_grade,
                ]))
                ->pluck('id');

            $classroom->setAttribute('assigned_readings_count', $documentIds->count());
            $assignedDocumentIds = $assignedDocumentIds->merge($documentIds);
        }

        $assignedDocumentIds = $assignedDocumentIds->unique()->values();
        $discussionSessionCount = $assignedDocumentIds->isEmpty()
            ? 0
            : $student->learningActivities()
                ->where('event_type', 'teacher_challenge')
                ->whereIn('document_id', $assignedDocumentIds)
                ->whereDate('activity_date', '>=', now()->startOfWeek()->toDateString())
                ->count();

        $summary = $gamification->summary($student);
        $completedReadingIds = $student->readingSessions()
            ->where(function ($query) {
                $query->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
            })
            ->pluck('document_id')
            ->merge($student->learningActivities()
                ->where('event_type', 'reading_completed')
                ->pluck('document_id'))
            ->unique();
        $completedReadings = $completedReadingIds->count();
        $completedChallenges = $student->learningActivities()
            ->whereIn('event_type', ['challenge_completed', 'quiz_timed_quiz', 'quiz_practice', 'teacher_challenge'])
            ->get(['document_id', 'event_type'])
            ->unique(fn ($activity) => $activity->document_id . ':' . $activity->event_type)
            ->count();
        $weeklyReadingCount = $student->readingSessions()
            ->where(function ($query) {
                $query->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
            })
            ->whereBetween('updated_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->distinct('document_id')
            ->count('document_id');

        if ($weeklyReadingCount === 0) {
            $weeklyReadingCount = $student->learningActivities()
                ->where('event_type', 'reading_completed')
                ->whereBetween('activity_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
                ->count();
        }

        return view('student.classrooms', [
            'classrooms' => $classrooms,
            'user' => $student,
            'assignedReadingCount' => $assignedDocumentIds->count(),
            'discussionSessionCount' => $discussionSessionCount,
            'summary' => $summary,
            'completedReadings' => $completedReadings,
            'completedChallenges' => $completedChallenges,
            'weeklyReadingCount' => $weeklyReadingCount,
            'joinCode' => strtoupper((string) $request->query('join_code', '')),
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
            return back()
                ->withErrors(['join_code' => 'Kode kelas tidak ditemukan atau sudah tidak aktif.'])
                ->withInput();
        }

        $student = $request->user();

        $now = now();
        $inserted = DB::table('classroom_memberships')->insertOrIgnore([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return redirect()->route('classrooms.index')->with('notice', 'Kamu sudah tergabung di kelas ini.');
        }

        return redirect()->route('classrooms.index')->with('notice', 'Berhasil bergabung dengan kelas '.$classroom->name.'.');
    }
}
