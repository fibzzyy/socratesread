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
        $classrooms = $user->classrooms()
            ->with('teacher:id,name')
            ->orderBy('teacher_classes.name')
            ->get();
        $selectedClassrooms = $classrooms
            ->filter(fn ($classroom) => $selectedGrade === 'all' || (string) $classroom->target_grade === $selectedGrade)
            ->values();
        $hasEnrolledClass = $selectedClassrooms->isNotEmpty();

        // Keep historical records so rejoining with the same account restores class progress.
        $classDocumentIds = $selectedClassrooms->isEmpty()
            ? []
            : Document::query()
                ->where(function ($query) use ($selectedClassrooms) {
                    foreach ($selectedClassrooms as $classroom) {
                        $query->orWhere(function ($classDocuments) use ($classroom) {
                            $classDocuments
                                ->where('uploaded_by', $classroom->teacher_id)
                                ->where('category', $classroom->name)
                                ->when($classroom->target_grade, fn ($documents) => $documents->whereIn('target_grade', [
                                    'all', (string) $classroom->target_grade,
                                ]));
                        });
                    }
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $summary = $gamification->summary($user, $selectedGrade, $classDocumentIds);
        $weeklyActivity = $gamification->weeklyActivity($user, $selectedGrade, $classDocumentIds);
        $recentActivities = $gamification->recentActivities($user, 5, $selectedGrade, $classDocumentIds);
        $completedDocIds = $user->readingSessions()
            ->whereIn('document_id', $classDocumentIds)
            ->where(function ($q) {
                $q->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
            })
            ->pluck('document_id')
            ->merge(
                $user->learningActivities()
                    ->whereIn('document_id', $classDocumentIds)
                    ->where('event_type', 'reading_completed')
                    ->pluck('document_id')
            )
            ->unique();
        $completedReadings = $completedDocIds->count();
        $completedChallenges = $user->learningActivities()
            ->whereIn('document_id', $classDocumentIds)
            ->where('event_type', 'challenge_completed')
            ->count();
        $weeklyReadingCount = $user->readingSessions()
            ->whereIn('document_id', $classDocumentIds)
            ->where(function ($q) {
                $q->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
            })
            ->whereBetween('updated_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->distinct('document_id')
            ->count('document_id');

        if ($weeklyReadingCount === 0) {
            $weeklyReadingCount = $user->learningActivities()
                ->whereIn('document_id', $classDocumentIds)
                ->where('event_type', 'reading_completed')
                ->whereBetween('activity_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
                ->count();
        }

        if ($isPriorGrade || ! $hasEnrolledClass) {
            $readingDocuments = collect();
            $activeSession = null;
            $challengeQuestion = null;
        } else {
            $readingDocuments = Document::query()
                ->where('is_published', true)
                ->whereIn('id', $classDocumentIds)
                ->with(['readingSessions' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->latest('started_at')
                    ->limit(1)])
                ->latest('created_at')
                ->limit(3)
                ->get();

            $activeSession = $user->readingSessions()
                ->with(['document.questions'])
                ->whereIn('document_id', $classDocumentIds)
                ->where('status', 'in_progress')
                ->latest('started_at')
                ->first();
            $challengeQuestion = $activeSession?->document?->questions?->first();
        }
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
            'user', 'selectedGrade', 'isPriorGrade', 'hasEnrolledClass',
        ));
    }
}
