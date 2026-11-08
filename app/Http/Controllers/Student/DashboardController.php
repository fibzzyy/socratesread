<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GamificationService $gamification): View|RedirectResponse
    {
        $user = auth()->user();
        if ($user->role === 'teacher') {
            return redirect()->route('teacher.dashboard');
        }

        // Simpan pilihan tingkat agar Dashboard dan Reading Room selalu memakai jenjang yang sama.
        $defaultGrade = (string) ($user->currentGrade() ?? ($user->entry_grade ?? 10));
        $selectedGrade = $request->query('grade', $request->session()->get('learning_grade', $defaultGrade));
        if (! in_array($selectedGrade, ['10', '11', '12', 'all'], true)) {
            $selectedGrade = $defaultGrade;
        }
        $request->session()->put('learning_grade', $selectedGrade);

        $selectedGradeNumeric = $selectedGrade !== 'all' ? (int) $selectedGrade : null;
        $isPriorGrade = $user->isPriorGrade($selectedGradeNumeric);

        $summary = $gamification->summary($user, $selectedGrade);
        $weeklyActivity = $gamification->weeklyActivity($user, $selectedGrade);
        $recentActivities = $gamification->recentActivities($user, 5, $selectedGrade);
        $completedReadings = $user->learningActivities()->where('event_type', 'reading_completed')->whereHas('document', fn ($documents) => $documents->whereIn('target_grade', $selectedGrade === 'all' ? ['all', '10', '11', '12'] : ['all', $selectedGrade]))->count();
        $completedChallenges = $user->learningActivities()->where('event_type', 'challenge_completed')->whereHas('document', fn ($documents) => $documents->whereIn('target_grade', $selectedGrade === 'all' ? ['all', '10', '11', '12'] : ['all', $selectedGrade]))->count();
        $weeklyReadingCount = $user->learningActivities()
            ->where('event_type', 'reading_completed')
            ->whereHas('document', fn ($documents) => $documents->whereIn(
                'target_grade',
                $selectedGrade === 'all' ? ['all', '10', '11', '12'] : ['all', $selectedGrade],
            ))
            ->whereBetween('activity_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
            ->count();

        // Jika tingkat yang dipilih adalah sebelum siswa mendaftar (halaman kosong untuk siswa pindahan)
        if ($isPriorGrade) {
            $readingDocuments = collect();
            $activeSession = null;
            $challengeQuestion = null;
        } else {
            $readingDocuments = Document::query()
                ->where('is_published', true)
                ->when($selectedGrade !== 'all', function ($query) use ($selectedGrade) {
                    $query->where(function ($sub) use ($selectedGrade) {
                        $sub->where('target_grade', 'all')
                            ->orWhere('target_grade', (string) $selectedGrade);
                    });
                })
                ->with(['readingSessions' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->latest('started_at')
                    ->limit(1)])
                ->latest('created_at')
                ->limit(3)
                ->get();

            $activeSession = $user->readingSessions()
                ->with(['document.questions'])
                ->where('status', 'in_progress')
                ->latest('started_at')
                ->first();
            $challengeQuestion = $activeSession?->document?->questions?->first();
        }

        $classrooms = $user->classrooms()->with('teacher:id,name')->orderBy('teacher_classes.name')->get();

        $hour = (int) now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('H');
        $greeting = match (true) {
            $hour >= 4 && $hour < 11 => 'Selamat pagi',
            $hour >= 11 && $hour < 15 => 'Selamat siang',
            $hour >= 15 && $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        return view('welcome', compact(
            'summary', 'weeklyActivity', 'recentActivities', 'completedReadings',
            'completedChallenges', 'weeklyReadingCount', 'readingDocuments',
            'activeSession', 'challengeQuestion',
            'classrooms', 'greeting',
            'user', 'selectedGrade', 'isPriorGrade',
        ));
    }
}
