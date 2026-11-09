<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TeacherClass;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AchievementController extends Controller
{
    public function index(Request $request, GamificationService $gamification): View
    {
        $user = auth()->user();

        // 1. Tentukan jenjang angkatan siswa saat ini (Kelas 10, 11, atau 12)
        $userCurrentGrade = $user->currentGrade() ?: (int) ($user->entry_grade ?: 10);
        if (! in_array($userCurrentGrade, [10, 11, 12], true)) {
            $userCurrentGrade = 10;
        }

        // 2. Ambil parameter filter angkatan (default ke angkatan siswa saat ini)
        $selectedGrade = (int) $request->query('grade', $userCurrentGrade);
        if (! in_array($selectedGrade, [10, 11, 12], true)) {
            $selectedGrade = $userCurrentGrade;
        }

        // 3. Ambil semua siswa di angkatan tersebut
        $currentYear = (int) now()->year;
        $cohortStudents = User::query()
            ->where('role', 'student')
            ->get()
            ->filter(function (User $s) use ($selectedGrade, $user) {
                // Selalu sertakan siswa yang sedang login jika sedang melihat angkatannya sendiri
                if ($s->id === $user->id) {
                    $grade = $s->currentGrade() ?: (int) ($s->entry_grade ?: 10);
                    return $grade === $selectedGrade;
                }

                $grade = $s->currentGrade() ?: (int) ($s->entry_grade ?: 10);
                return $grade === $selectedGrade;
            })
            ->values();

        // Jika data angkatan kosong (misal database baru), sertakan user saat ini
        if ($cohortStudents->isEmpty() && $userCurrentGrade === $selectedGrade) {
            $cohortStudents = collect([$user]);
        }

        $studentIds = $cohortStudents->pluck('id')->all();

        // Ambil data agregat aktivitas pembelajaran untuk seluruh siswa di angkatan ini
        $activities = DB::table('learning_activities')
            ->whereIn('user_id', $studentIds)
            ->get();

        $teacherSubmissions = DB::table('student_teacher_challenge_submissions')
            ->whereIn('user_id', $studentIds)
            ->where('status', 'completed')
            ->get();

        $quizAttempts = DB::table('student_quiz_attempts')
            ->whereIn('user_id', $studentIds)
            ->where('status', 'completed')
            ->get();

        // Susun Leaderboard Daya Saing Angkatan
        $leaderboard = $cohortStudents->map(function (User $student) use ($user, $activities, $teacherSubmissions, $quizAttempts) {
            $userActivities = $activities->where('user_id', $student->id);
            $userTeacherSubs = $teacherSubmissions->where('user_id', $student->id);
            $userQuizzes = $quizAttempts->where('user_id', $student->id);

            $totalXp = (int) $userActivities->sum('xp_awarded');
            $level = intdiv($totalXp, 100) + 1;

            $readingsCount = $userActivities->where('event_type', 'reading_completed')->count();
            $challengesCount = $userTeacherSubs->count();
            $avgChallengeScore = $challengesCount > 0 ? (int) round($userTeacherSubs->avg('score')) : 0;

            $quizzesCount = $userQuizzes->count();
            $avgQuizScore = $quizzesCount > 0 ? (int) round($userQuizzes->avg('score')) : 0;

            $initials = collect(explode(' ', $student->name))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode('');

            return [
                'id' => $student->id,
                'name' => $student->name,
                'avatar_url' => $student->avatarUrl(),
                'initials' => mb_strtoupper($initials),
                'is_current_user' => $student->id === $user->id,
                'grade' => $student->currentGrade() ?: (int) ($student->entry_grade ?: 10),
                'total_xp' => $totalXp,
                'level' => $level,
                'readings_count' => $readingsCount,
                'challenges_count' => $challengesCount,
                'avg_challenge_score' => $avgChallengeScore,
                'quizzes_count' => $quizzesCount,
                'avg_quiz_score' => $avgQuizScore,
            ];
        })
        // Pengurutan daya saing: XP terbanyak, tantangan guru selesai, skor tantangan, kuis selesai, nama
        ->sortBy([
            ['total_xp', 'desc'],
            ['challenges_count', 'desc'],
            ['avg_challenge_score', 'desc'],
            ['quizzes_count', 'desc'],
            ['readings_count', 'desc'],
            ['name', 'asc'],
        ])
        ->values()
        ->map(function ($item, $index) {
            $rank = $index + 1;
            $item['rank'] = $rank;
            return (object) $item;
        });

        $topThree = $leaderboard->take(3);
        $currentUserEntry = $leaderboard->firstWhere('is_current_user', true);
        $currentUserRank = $currentUserEntry?->rank;

        $cohortSummary = [
            'total_students' => $leaderboard->count(),
            'total_xp' => (int) $leaderboard->sum('total_xp'),
            'total_challenges' => (int) $leaderboard->sum('challenges_count'),
            'avg_challenge_score' => $leaderboard->count() > 0 ? (int) round($leaderboard->avg('avg_challenge_score')) : 0,
        ];

        // 4. Hitung Lencana Prestasi Belajar (Personal Badges)
        $personalStats = $gamification->summary($user);
        $totalReadingsCompleted = $user->learningActivities()->where('event_type', 'reading_completed')->count();
        $totalChallengesCompleted = DB::table('student_teacher_challenge_submissions')->where('user_id', $user->id)->where('status', 'completed')->count();
        $bestQuizScore = (int) DB::table('student_quiz_attempts')->where('user_id', $user->id)->where('status', 'completed')->max('score');
        $hasPerfectScore = DB::table('student_quiz_attempts')->where('user_id', $user->id)->where('status', 'completed')->where('score', 100)->exists()
            || DB::table('student_teacher_challenge_submissions')->where('user_id', $user->id)->where('status', 'completed')->where('score', 100)->exists();

        $badges = [
            [
                'id' => 'first_reader',
                'name' => 'Langkah Pertama',
                'description' => 'Menyelesaikan bacaan materi pertama.',
                'icon' => 'book-open-text',
                'color' => 'blue',
                'is_unlocked' => $totalReadingsCompleted >= 1,
                'progress' => min(1, $totalReadingsCompleted) . '/1',
            ],
            [
                'id' => 'avid_reader',
                'name' => 'Pembaca Tekun',
                'description' => 'Menyelesaikan minimal 3 materi bacaan.',
                'icon' => 'library',
                'color' => 'emerald',
                'is_unlocked' => $totalReadingsCompleted >= 3,
                'progress' => min(3, $totalReadingsCompleted) . '/3',
            ],
            [
                'id' => 'critical_thinker',
                'name' => 'Penakluk Challenge',
                'description' => 'Menuntaskan tantangan tugas dari guru.',
                'icon' => 'zap',
                'color' => 'amber',
                'is_unlocked' => $totalChallengesCompleted >= 1,
                'progress' => min(1, $totalChallengesCompleted) . '/1',
            ],
            [
                'id' => 'quiz_master',
                'name' => 'Akurasi Hebat',
                'description' => 'Mendapatkan nilai 80 ke atas pada tantangan/kuis.',
                'icon' => 'target',
                'color' => 'purple',
                'is_unlocked' => $bestQuizScore >= 80,
                'progress' => ($bestQuizScore > 0 ? "Skor {$bestQuizScore}" : '0') . '/80',
            ],
            [
                'id' => 'class_podium',
                'name' => 'Bintang Angkatan',
                'description' => 'Meraih posisi Top 3 di peringkat angkatan kelas.',
                'icon' => 'crown',
                'color' => 'amber',
                'is_unlocked' => $currentUserRank !== null && $currentUserRank <= 3,
                'progress' => $currentUserRank ? "Posisi #{$currentUserRank}" : 'Belum ada',
            ],
            [
                'id' => 'perfect_score',
                'name' => 'Nilai Sempurna',
                'description' => 'Meraih skor sempurna 100/100 pada tantangan guru/kuis.',
                'icon' => 'trophy',
                'color' => 'rose',
                'is_unlocked' => $hasPerfectScore,
                'progress' => $hasPerfectScore ? '100/100' : 'Belum',
            ],
        ];

        return view('student.achievements', compact(
            'userCurrentGrade',
            'selectedGrade',
            'leaderboard',
            'topThree',
            'currentUserRank',
            'cohortSummary',
            'badges',
            'personalStats'
        ));
    }
}
