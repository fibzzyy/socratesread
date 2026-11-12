<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentDetailController extends Controller
{
    public function show(Request $request, User $student): JsonResponse
    {
        $teacherId = $request->user()->id;

        if ($student->role !== 'student') {
            abort(404);
        }

        $isEnrolled = DB::table('classroom_memberships')
            ->join('teacher_classes', 'teacher_classes.id', '=', 'classroom_memberships.classroom_id')
            ->where('teacher_classes.teacher_id', $teacherId)
            ->where('classroom_memberships.student_id', $student->id)
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'Siswa tidak terdaftar di kelas Anda.');
        }

        $sessions = ReadingSession::query()
            ->with(['document' => fn ($q) => $q->select('id', 'title', 'category', 'target_grade', 'estimated_minutes')])
            ->withCount('answers')
            ->where('user_id', $student->id)
            ->whereHas('document', fn ($q) => $q->where('uploaded_by', $teacherId))
            ->latest('updated_at')
            ->get();

        $classes = DB::table('classroom_memberships')
            ->join('teacher_classes', 'teacher_classes.id', '=', 'classroom_memberships.classroom_id')
            ->where('teacher_classes.teacher_id', $teacherId)
            ->where('classroom_memberships.student_id', $student->id)
            ->pluck('teacher_classes.name');

        $sessionXp = DB::table('learning_activities')
            ->where('user_id', $student->id)
            ->whereIn('document_id', function ($query) use ($teacherId) {
                $query->select('id')->from('documents')->where('uploaded_by', $teacherId);
            })
            ->sum('xp_awarded');

        $currentYear = (int) now()->year;
        $grade = $student->entry_grade 
            ? 'Kelas ' . min(12, $student->entry_grade + max(0, $currentYear - ($student->enrollment_year ?? $currentYear)))
            : null;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'school_origin' => $student->school_origin ?: '-',
                'grade' => $grade,
                'avatar_url' => $student->avatarUrl(),
                'classes' => $classes,
                'session_count' => $sessions->count(),
                'completed_count' => $sessions->filter(fn ($s) => $s->status === 'completed' || $s->progress_percent >= 100)->count(),
                'average_progress' => (int) round($sessions->avg('progress_percent') ?? 0),
                'earned_xp' => (int) $sessionXp,
                'last_active' => $sessions->first()?->updated_at ? $sessions->first()->updated_at->locale('id')->diffForHumans() : 'Belum aktif',
            ],
            'sessions' => $sessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'title' => $s->document->title ?? 'Materi Membaca',
                    'category' => $s->document->category ?? 'Literasi',
                    'target_grade' => $s->document->targetGradeLabel() ?? 'Semua Kelas',
                    'progress_percent' => $s->progress_percent,
                    'status' => ($s->status === 'completed' || $s->progress_percent >= 100) ? 'Selesai' : 'Sedang membaca',
                    'is_completed' => ($s->status === 'completed' || $s->progress_percent >= 100),
                    'answers_count' => $s->answers_count,
                    'started_at' => $s->started_at ? $s->started_at->locale('id')->translatedFormat('d M Y H:i') : '-',
                    'completed_at' => $s->completed_at ? $s->completed_at->locale('id')->translatedFormat('d M Y H:i') : null,
                ];
            }),
        ]);
    }
}
