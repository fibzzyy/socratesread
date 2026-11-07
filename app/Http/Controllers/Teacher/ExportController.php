<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportCsv(Request $request): StreamedResponse
    {
        $teacherId = $request->user()->id;

        $enrolledStudentIds = fn () => DB::table('classroom_memberships')
            ->join('teacher_classes', 'teacher_classes.id', '=', 'classroom_memberships.classroom_id')
            ->join('users as enrolled_users', 'enrolled_users.id', '=', 'classroom_memberships.student_id')
            ->where('teacher_classes.teacher_id', $teacherId)
            ->where('enrolled_users.role', 'student')
            ->select('classroom_memberships.student_id');

        $sessionXp = DB::table('learning_activities')
            ->select('reading_session_id')
            ->selectRaw('SUM(xp_awarded) as earned_xp')
            ->groupBy('reading_session_id');

        $students = DB::table('users')
            ->leftJoin('reading_sessions as sessions', function ($join) use ($teacherId) {
                $join->on('sessions.user_id', '=', 'users.id')
                    ->whereIn('sessions.document_id', function ($query) use ($teacherId) {
                        $query->select('id')->from('documents')->where('uploaded_by', $teacherId);
                    });
            })
            ->leftJoinSub($sessionXp, 'session_xp', 'session_xp.reading_session_id', '=', 'sessions.id')
            ->where('users.role', 'student')
            ->whereIn('users.id', $enrolledStudentIds())
            ->select('users.id', 'users.name', 'users.email', 'users.school_origin', 'users.entry_grade', 'users.enrollment_year')
            ->selectRaw('COUNT(DISTINCT sessions.id) as session_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN sessions.status = 'completed' THEN sessions.id END) as completed_count")
            ->selectRaw('ROUND(COALESCE(AVG(sessions.progress_percent), 0)) as average_progress')
            ->selectRaw('COALESCE(SUM(session_xp.earned_xp), 0) as earned_xp')
            ->selectRaw('MAX(sessions.updated_at) as last_activity_at')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.school_origin', 'users.entry_grade', 'users.enrollment_year')
            ->orderBy('users.name')
            ->get();

        $studentClasses = DB::table('classroom_memberships')
            ->join('teacher_classes', 'teacher_classes.id', '=', 'classroom_memberships.classroom_id')
            ->where('teacher_classes.teacher_id', $teacherId)
            ->select('classroom_memberships.student_id', 'teacher_classes.name')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->pluck('name')->implode(', '));

        $fileName = 'Rekap_Progres_Siswa_SocratesRead_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($students, $studentClasses) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM so Microsoft Excel renders accented/UTF-8 characters properly
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'No',
                'Nama Siswa',
                'Email',
                'Asal Sekolah',
                'Tingkat Kelas',
                'Kelas Diikuti',
                'Total Sesi Membaca',
                'Sesi Selesai',
                'Rata-rata Progres (%)',
                'XP Bacaan Guru',
                'Aktivitas Terakhir',
            ]);

            $currentYear = (int) now()->year;
            $index = 1;
            foreach ($students as $student) {
                $grade = $student->entry_grade 
                    ? 'Kelas ' . min(12, $student->entry_grade + max(0, $currentYear - ($student->enrollment_year ?? $currentYear)))
                    : '-';

                fputcsv($handle, [
                    $index++,
                    $student->name,
                    $student->email,
                    $student->school_origin ?: '-',
                    $grade,
                    $studentClasses->get($student->id, '-'),
                    $student->session_count,
                    $student->completed_count,
                    $student->average_progress . '%',
                    $student->earned_xp,
                    $student->last_activity_at ? \Illuminate\Support\Carbon::parse($student->last_activity_at)->locale('id')->translatedFormat('d F Y H:i') : 'Belum ada',
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
