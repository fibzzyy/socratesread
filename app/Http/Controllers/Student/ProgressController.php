<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TeacherClass;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __invoke(Request $request, GamificationService $gamification): View
    {
        $user = auth()->user();

        // 1. Ambil daftar kelas yang diikuti siswa
        $classrooms = $user->classrooms()
            ->with('teacher:id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        // 2. Cek apakah ada kelas yang dipilih
        $selectedClassroomId = $request->query('classroom');
        $activeClassroom = $selectedClassroomId ? $classrooms->firstWhere('id', (int) $selectedClassroomId) : null;

        // 3. Ringkasan Breakdown Setiap Kelas untuk ditampilkan di halaman utama
        $classroomCards = $classrooms->map(function (TeacherClass $classroom) use ($user) {
            $classDocs = $this->getDocumentsForClassroom($classroom);
            $docIds = $classDocs->pluck('id')->all();
            $totalDocs = count($docIds);

            $completedReadings = $user->readingSessions()
                ->whereIn('document_id', $docIds)
                ->where(function ($q) {
                    $q->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
                })
                ->pluck('document_id')
                ->merge(
                    $user->learningActivities()
                        ->where('event_type', 'reading_completed')
                        ->whereIn('document_id', $docIds)
                        ->pluck('document_id')
                )
                ->unique()
                ->count();

            $completedChallenges = $user->learningActivities()
                ->whereIn('event_type', ['challenge_completed', 'quiz_timed_quiz', 'quiz_practice', 'teacher_challenge'])
                ->whereIn('document_id', $docIds)
                ->distinct('document_id')
                ->count('document_id');

            $xpEarned = (int) $user->learningActivities()
                ->whereIn('document_id', $docIds)
                ->sum('xp_awarded');

            $progressPercent = $totalDocs > 0 ? (int) round(($completedReadings / $totalDocs) * 100) : 0;

            return [
                'classroom' => $classroom,
                'teacher_name' => $classroom->teacher?->name ?? 'Guru Pengajar',
                'target_grade_label' => $classroom->targetGradeLabel(),
                'total_documents' => $totalDocs,
                'completed_readings' => $completedReadings,
                'completed_challenges' => $completedChallenges,
                'xp_earned' => $xpEarned,
                'progress_percent' => min(100, $progressPercent),
            ];
        });

        // 4. Hitung metrik: Jika kelas dipilih, hitung khusus kelas itu. Jika tidak, hitung global.
        if ($activeClassroom) {
            $activeDocs = $this->getDocumentsForClassroom($activeClassroom);
            $filterDocumentIds = $activeDocs->pluck('id')->all();
            $totalDocsInScope = count($filterDocumentIds);
        } else {
            $filterDocumentIds = null;
            $totalDocsInScope = Document::query()->where('is_published', true)->count();
        }

        $summary = $gamification->summary($user, $filterDocumentIds);
        $weeklyActivity = $gamification->weeklyActivity($user, $filterDocumentIds);
        $recentActivities = $gamification->recentActivities($user, 8, $filterDocumentIds);

        $completedDocIds = $user->readingSessions()
            ->when($filterDocumentIds !== null, fn ($q) => $q->whereIn('document_id', $filterDocumentIds))
            ->where(function ($q) {
                $q->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
            })
            ->pluck('document_id')
            ->merge(
                $user->learningActivities()
                    ->where('event_type', 'reading_completed')
                    ->when($filterDocumentIds !== null, fn ($q) => $q->whereIn('document_id', $filterDocumentIds))
                    ->pluck('document_id')
            )
            ->unique();
        $completedReadings = $completedDocIds->count();

        $challengesQuery = $user->learningActivities()
            ->whereIn('event_type', ['challenge_completed', 'quiz_timed_quiz', 'quiz_practice', 'teacher_challenge'])
            ->when($filterDocumentIds !== null, fn ($q) => $q->whereIn('document_id', $filterDocumentIds));
        $completedChallenges = $challengesQuery->distinct('document_id')->count('document_id');

        $weeklyReadingCount = $user->readingSessions()
            ->when($filterDocumentIds !== null, fn ($q) => $q->whereIn('document_id', $filterDocumentIds))
            ->where(function ($q) {
                $q->where('status', 'completed')->orWhere('progress_percent', '>=', 100);
            })
            ->whereBetween('updated_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->distinct('document_id')
            ->count('document_id');

        if ($weeklyReadingCount === 0) {
            $weeklyReadingCount = $user->learningActivities()
                ->where('event_type', 'reading_completed')
                ->whereBetween('activity_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
                ->when($filterDocumentIds !== null, fn ($q) => $q->whereIn('document_id', $filterDocumentIds))
                ->count();
        }

        // 5. Kompetensi Membaca (5 skill sokratik) dengan persentase realistis dan realtime
        $skillsCompetency = $this->getSkillsCompetency($user, $filterDocumentIds, $totalDocsInScope);

        return view('student.progress', compact(
            'classrooms',
            'activeClassroom',
            'classroomCards',
            'summary',
            'weeklyActivity',
            'recentActivities',
            'completedReadings',
            'completedChallenges',
            'weeklyReadingCount',
            'skillsCompetency',
            'totalDocsInScope'
        ));
    }

    /**
     * Mengambil seluruh dokumen bacaan yang sesuai untuk kelas tertentu.
     */
    protected function getDocumentsForClassroom(TeacherClass $classroom): Collection
    {
        $allDocs = Document::query()
            ->where('is_published', true)
            ->where('uploaded_by', $classroom->teacher_id)
            ->get();

        $classDocs = $allDocs->filter(function ($doc) use ($classroom) {
            $cName = mb_strtolower(trim($classroom->name));
            $dCat = mb_strtolower(trim($doc->category));
            return $dCat === $cName || str_contains($cName, $dCat) || str_contains($dCat, $cName);
        });

        if ($classDocs->isEmpty()) {
            $classDocs = $allDocs->filter(function ($doc) use ($classroom) {
                return ! $classroom->target_grade
                    || $doc->target_grade === 'all'
                    || (int) $doc->target_grade === (int) $classroom->target_grade;
            });
        }

        return $classDocs->values();
    }

    /**
     * Menghitung capaian 5 kompetensi membaca Sokratik secara realtime & proporsional terhadap materi yang ada.
     *
     * @param ?array<int, int> $filterDocumentIds
     */
    protected function getSkillsCompetency(User $user, ?array $filterDocumentIds, int $totalDocs): Collection
    {
        $defaultSkills = [
            'Memahami gagasan',
            'Menemukan bukti',
            'Menganalisis asumsi',
            'Menjelaskan alasan',
            'Refleksi dan penerapan',
        ];

        // Target latihan dasar (minimal 5 materi agar bar bertambah proporsional)
        $targetBase = max(5, $totalDocs);

        $query = $user->readingSessions()
            ->join('student_answers', 'student_answers.reading_session_id', '=', 'reading_sessions.id')
            ->join('socratic_questions', 'socratic_questions.id', '=', 'student_answers.socratic_question_id');

        if ($filterDocumentIds !== null) {
            $query->whereIn('reading_sessions.document_id', $filterDocumentIds);
        }

        $practicedBySkill = $query
            ->selectRaw('socratic_questions.skill, COUNT(DISTINCT reading_sessions.document_id) as doc_count')
            ->groupBy('socratic_questions.skill')
            ->pluck('doc_count', 'socratic_questions.skill');

        return collect($defaultSkills)->map(function (string $skillName) use ($practicedBySkill, $targetBase) {
            $practicedDocs = (int) ($practicedBySkill[$skillName] ?? 0);
            $percent = min(100, (int) round(($practicedDocs / $targetBase) * 100));

            return (object) [
                'skill' => $skillName,
                'practiced_count' => $practicedDocs,
                'target_count' => $targetBase,
                'percent' => $percent,
            ];
        });
    }
}
