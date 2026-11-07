<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $teacherId = auth()->id();
        $enrolledStudentIds = fn () => DB::table('classroom_memberships')
            ->join('teacher_classes', 'teacher_classes.id', '=', 'classroom_memberships.classroom_id')
            ->join('users as enrolled_users', 'enrolled_users.id', '=', 'classroom_memberships.student_id')
            ->where('teacher_classes.teacher_id', $teacherId)
            ->where('enrolled_users.role', 'student')
            ->select('classroom_memberships.student_id');
        $documents = DB::table('documents')->where('uploaded_by', $teacherId);
        $totals = DB::table('reading_sessions')
            ->join('documents', 'documents.id', '=', 'reading_sessions.document_id')
            ->where('documents.uploaded_by', $teacherId)
            ->whereIn('reading_sessions.user_id', $enrolledStudentIds())
            ->selectRaw('COUNT(*) as session_count')
            ->selectRaw('COUNT(DISTINCT reading_sessions.user_id) as student_count')
            ->selectRaw("SUM(CASE WHEN reading_sessions.status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('COALESCE(AVG(reading_sessions.progress_percent), 0) as average_progress')
            ->first();

        $sessionXp = DB::table('learning_activities')
            ->select('reading_session_id')
            ->selectRaw('SUM(xp_awarded) as earned_xp')
            ->groupBy('reading_session_id');

        $students = DB::table('reading_sessions as sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->join('documents', 'documents.id', '=', 'sessions.document_id')
            ->leftJoinSub($sessionXp, 'session_xp', 'session_xp.reading_session_id', '=', 'sessions.id')
            ->where('documents.uploaded_by', $teacherId)
            ->where('users.role', 'student')
            ->whereIn('users.id', $enrolledStudentIds())
            ->select('users.id', 'users.name', 'users.entry_grade', 'users.enrollment_year')
            ->selectRaw('COUNT(DISTINCT sessions.id) as session_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN sessions.status = 'completed' THEN sessions.id END) as completed_count")
            ->selectRaw('ROUND(AVG(sessions.progress_percent)) as average_progress')
            ->selectRaw('COALESCE(SUM(session_xp.earned_xp), 0) as earned_xp')
            ->selectRaw('MAX(sessions.updated_at) as last_activity_at')
            ->groupBy('users.id', 'users.name', 'users.entry_grade', 'users.enrollment_year')
            ->orderByDesc('last_activity_at')
            ->paginate(10)
            ->withQueryString();

        $weeklyRows = DB::table('learning_activities')
            ->join('documents', 'documents.id', '=', 'learning_activities.document_id')
            ->where('documents.uploaded_by', $teacherId)
            ->whereIn('learning_activities.user_id', $enrolledStudentIds())
            ->whereBetween('learning_activities.activity_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
            ->select('learning_activities.activity_date')
            ->selectRaw('COUNT(*) as activity_count')
            ->selectRaw('COALESCE(SUM(learning_activities.xp_awarded), 0) as xp_total')
            ->groupBy('learning_activities.activity_date')
            ->get()
            ->keyBy(fn ($row) => (string) $row->activity_date);

        $weeklyActivity = collect(range(0, 6))->map(function (int $offset) use ($weeklyRows) {
            $date = now()->startOfDay()->subDays(6 - $offset);
            $row = $weeklyRows->get($date->toDateString());

            return [
                'label' => $date->locale('id')->translatedFormat('D'),
                'date' => $date,
                'activity_count' => (int) ($row->activity_count ?? 0),
                'xp_total' => (int) ($row->xp_total ?? 0),
            ];
        });

        $recentActivities = DB::table('learning_activities')
            ->join('documents', 'documents.id', '=', 'learning_activities.document_id')
            ->join('users', 'users.id', '=', 'learning_activities.user_id')
            ->where('documents.uploaded_by', $teacherId)
            ->where('users.role', 'student')
            ->whereIn('users.id', $enrolledStudentIds())
            ->select('learning_activities.*', 'documents.title as document_title', 'users.name as student_name')
            ->latest('learning_activities.created_at')
            ->limit(6)
            ->get();

        $socraticSkills = DB::table('student_answers')
            ->join('reading_sessions', 'reading_sessions.id', '=', 'student_answers.reading_session_id')
            ->join('documents', 'documents.id', '=', 'reading_sessions.document_id')
            ->join('socratic_questions', 'socratic_questions.id', '=', 'student_answers.socratic_question_id')
            ->where('documents.uploaded_by', $teacherId)
            ->whereIn('reading_sessions.user_id', $enrolledStudentIds())
            ->select('socratic_questions.skill')
            ->selectRaw('COUNT(student_answers.id) as answer_count')
            ->selectRaw('COUNT(DISTINCT reading_sessions.user_id) as student_count')
            ->groupBy('socratic_questions.skill')
            ->orderByDesc('answer_count')
            ->get();

        $documentCount = (clone $documents)->count();
        $studentCount = (int) ($totals->student_count ?? 0);
        $sessionCount = (int) ($totals->session_count ?? 0);
        $completedCount = (int) ($totals->completed_count ?? 0);
        $averageProgress = (int) round((float) ($totals->average_progress ?? 0));

        return view('teacher.dashboard', compact(
            'documentCount', 'studentCount', 'sessionCount', 'completedCount',
            'averageProgress', 'students', 'weeklyActivity', 'recentActivities',
            'socraticSkills',
        ));
    }
}
