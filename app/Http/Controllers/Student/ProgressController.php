<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ReadingCoachMessage;
use App\Models\StudentAnswer;
use App\Models\StudentQuizAttempt;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherChallenge;
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

            $completedChallenges = $this->countCompletedChallenges($user, $docIds);

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

        $completedChallenges = $this->countCompletedChallenges($user, $filterDocumentIds);

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

        // 5. Kompetensi Membaca (5 keterampilan Sokratik berbasis 4 aktivitas nyata)
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
     * Menghitung total tantangan selesai (baik Challenge Socrates maupun Tantangan Guru) secara terpisah.
     *
     * @param ?array<int, int> $filterDocumentIds
     */
    protected function countCompletedChallenges(User $user, ?array $filterDocumentIds): int
    {
        if ($filterDocumentIds !== null && empty($filterDocumentIds)) {
            return 0;
        }

        return $user->learningActivities()
            ->whereIn('event_type', ['challenge_completed', 'quiz_timed_quiz', 'quiz_practice', 'teacher_challenge'])
            ->when($filterDocumentIds !== null, fn ($q) => $q->whereIn('document_id', $filterDocumentIds))
            ->get(['document_id', 'event_type'])
            ->unique(fn ($row) => $row->document_id . ':' . $row->event_type)
            ->count();
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
     * Menghitung capaian 5 Kompetensi Membaca Sokratik secara realtime dari 4 aktivitas nyata:
     * 1. Ketuntasan Baca (ReadingSession)
     * 2. Akurasi Kuis PG (StudentQuizAttempt)
     * 3. Kualitas & Kekritisan Analisis Tantangan Guru & Sokratik (StudentTeacherChallengeSubmission + StudentAnswer)
     * 4. Keaktifan Bertanya Bermakna ke Tutor AI Tanya Socrates (ReadingCoachMessage)
     *
     * @param ?array<int, int> $filterDocumentIds
     */
    protected function getSkillsCompetency(User $user, ?array $filterDocumentIds, int $totalDocs): Collection
    {
        $scopeDocIds = $filterDocumentIds !== null
            ? array_values(array_unique(array_map('intval', $filterDocumentIds)))
            : Document::query()->where('is_published', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $docCount = count($scopeDocIds);

        $defaultSkills = [
            'Memahami gagasan' => 'Dari ketuntasan baca, akurasi kuis, dan pemahaman konsep inti',
            'Menemukan bukti' => 'Dari akurasi fakta bacaan dan ketepatan bukti pada jawaban esai',
            'Menganalisis asumsi' => 'Dari kedalaman penalaran kritis soal Medium–Hard & parafrase mandiri',
            'Menjelaskan alasan' => 'Dari keaktifan bertanya di Tanya Socrates & uraian alasan esai',
            'Refleksi dan penerapan' => 'Dari konsistensi penyelesaian materi, kuis, tantangan, dan diskusi AI',
        ];

        if ($docCount === 0) {
            return collect($defaultSkills)->map(fn (string $desc, string $skillName) => (object) [
                'skill' => $skillName,
                'practiced_count' => 0,
                'target_count' => 0,
                'percent' => 0,
                'description' => $desc,
            ])->values();
        }

        // 1. Reading completion per document
        $readingSessions = $user->readingSessions()
            ->whereIn('document_id', $scopeDocIds)
            ->get(['document_id', 'status', 'progress_percent'])
            ->groupBy('document_id');

        $readingActivities = $user->learningActivities()
            ->where('event_type', 'reading_completed')
            ->whereIn('document_id', $scopeDocIds)
            ->pluck('document_id')
            ->flip();

        // 2. Best quiz score per document
        $quizScores = StudentQuizAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('document_id', $scopeDocIds)
            ->where('status', 'completed')
            ->get(['document_id', 'score'])
            ->groupBy('document_id')
            ->map(fn ($rows) => (float) $rows->max('score'));

        // 3. Teacher Challenge submissions & difficulty metadata
        $teacherSubs = StudentTeacherChallengeSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('document_id', $scopeDocIds)
            ->where('status', 'completed')
            ->get(['document_id', 'score', 'answers'])
            ->keyBy('document_id');

        $docsWithTeacherChallenges = TeacherChallenge::query()
            ->whereIn('document_id', $scopeDocIds)
            ->distinct('document_id')
            ->pluck('document_id')
            ->flip();

        // 4. Legacy Socratic Challenge answers per document
        $socraticAnswersByDoc = StudentAnswer::query()
            ->join('reading_sessions', 'reading_sessions.id', '=', 'student_answers.reading_session_id')
            ->where('reading_sessions.user_id', $user->id)
            ->whereIn('reading_sessions.document_id', $scopeDocIds)
            ->get(['reading_sessions.document_id', 'student_answers.score', 'student_answers.evaluation'])
            ->groupBy('document_id');

        // 5. Meaningful Tanya Socrates chat turns per document
        $coachTurnsByDoc = ReadingCoachMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('document_id', $scopeDocIds)
            ->where('role', 'user')
            ->where('is_meaningful', true)
            ->selectRaw('document_id, COUNT(*) as msg_count')
            ->groupBy('document_id')
            ->pluck('msg_count', 'document_id');

        $skillTotals = array_fill_keys(array_keys($defaultSkills), 0.0);
        $skillPracticedDocs = array_fill_keys(array_keys($defaultSkills), 0);

        foreach ($scopeDocIds as $docId) {
            // A. Skor Baca (0-100)
            $sessions = $readingSessions->get($docId, collect());
            $bestProgress = (float) ($sessions->max('progress_percent') ?? 0);
            $isReadDone = $sessions->contains(fn ($s) => $s->status === 'completed' || $s->progress_percent >= 100)
                || $readingActivities->has($docId);
            $readScore = $isReadDone ? 100.0 : min(99.0, max(0.0, $bestProgress));

            // B. Skor Kuis PG (0-100)
            $hasQuiz = $quizScores->has($docId);
            $quizScore = $hasQuiz ? (float) $quizScores->get($docId) : 0.0;

            // C. Skor & Analisis Tantangan (Teacher Challenge + Socratic Answers)
            $hasTcSub = $teacherSubs->has($docId);
            $hasSocraticAns = $socraticAnswersByDoc->has($docId);

            $challengeOverall = 0.0;
            $evidenceMetric = 0.0;
            $criticalMetric = 0.0;
            $explanationMetric = 0.0;

            if ($hasTcSub) {
                $sub = $teacherSubs->get($docId);
                $challengeOverall = (float) $sub->score;
                $breakdown = is_array($sub->answers) ? $sub->answers : [];

                $essayQualities = [];
                $evidenceHits = 0;
                $essayCount = 0;
                $criticalScores = [];

                foreach ($breakdown as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }
                    $type = $entry['type'] ?? 'essay';
                    $diff = strtolower((string) ($entry['difficulty'] ?? 'medium'));

                    if ($type === 'essay') {
                        $essayCount++;
                        $qPct = (float) ($entry['quality_percent'] ?? 0);
                        $isCopied = (bool) ($entry['is_copied'] ?? false);
                        $hasEv = (bool) ($entry['has_evidence'] ?? false);

                        if ($hasEv || $qPct >= 75) {
                            $evidenceHits++;
                        }
                        $effectiveQuality = $isCopied ? min(55.0, $qPct) : $qPct;
                        $essayQualities[] = $effectiveQuality;

                        if (in_array($diff, ['medium', 'hard'], true)) {
                            $criticalScores[] = $effectiveQuality;
                        }
                    } else {
                        $pgScore = ! empty($entry['is_correct']) ? 100.0 : 0.0;
                        if (in_array($diff, ['medium', 'hard'], true)) {
                            $criticalScores[] = $pgScore;
                        }
                    }
                }

                $avgEssay = ! empty($essayQualities) ? array_sum($essayQualities) / count($essayQualities) : $challengeOverall;
                $evidenceMetric = $essayCount > 0
                    ? min(100.0, ($avgEssay * 0.7) + (($evidenceHits / $essayCount) * 30.0))
                    : $challengeOverall;
                $criticalMetric = ! empty($criticalScores)
                    ? array_sum($criticalScores) / count($criticalScores)
                    : $challengeOverall;
                $explanationMetric = $avgEssay;
            } elseif ($hasSocraticAns) {
                $ansRows = $socraticAnswersByDoc->get($docId);
                $scores = $ansRows->map(fn ($r) => $r->score !== null ? (float) $r->score : 75.0)->all();
                $avgSoc = ! empty($scores) ? array_sum($scores) / count($scores) : 0.0;
                $challengeOverall = $avgSoc;
                $evidenceMetric = $avgSoc;
                $criticalMetric = $avgSoc;
                $explanationMetric = $avgSoc;
            }

            // D. Keaktifan Bertanya ke Tutor AI Tanya Socrates (0-100)
            $coachTurns = (int) ($coachTurnsByDoc[$docId] ?? 0);
            $coachScore = min(100.0, $coachTurns * 35.0);

            // Jika guru belum membuat tantangan pada materi ini, distribusikan bobot ke komponen baca + kuis secara adil
            $hasAnyChallengeOnDoc = $docsWithTeacherChallenges->has($docId) || $hasTcSub || $hasSocraticAns;

            if ($hasAnyChallengeOnDoc) {
                $docSkillScores = [
                    'Memahami gagasan' => ($readScore * 0.25) + ($quizScore * 0.40) + ($challengeOverall * 0.35),
                    'Menemukan bukti' => ($quizScore * 0.35) + ($evidenceMetric * 0.45) + ($readScore * 0.20),
                    'Menganalisis asumsi' => ($criticalMetric * 0.55) + ($quizScore * 0.30) + ($coachScore * 0.15),
                    'Menjelaskan alasan' => ($coachScore * 0.35) + ($explanationMetric * 0.45) + ($quizScore * 0.20),
                    'Refleksi dan penerapan' => ($readScore * 0.20) + ($quizScore * 0.25) + ($challengeOverall * 0.40) + ($coachScore * 0.15),
                ];
            } else {
                $docSkillScores = [
                    'Memahami gagasan' => ($readScore * 0.40) + ($quizScore * 0.60),
                    'Menemukan bukti' => ($readScore * 0.35) + ($quizScore * 0.65),
                    'Menganalisis asumsi' => ($quizScore * 0.70) + ($coachScore * 0.30),
                    'Menjelaskan alasan' => ($coachScore * 0.55) + ($quizScore * 0.45),
                    'Refleksi dan penerapan' => ($readScore * 0.35) + ($quizScore * 0.45) + ($coachScore * 0.20),
                ];
            }

            foreach ($docSkillScores as $skillName => $val) {
                $clamped = min(100.0, max(0.0, $val));
                $skillTotals[$skillName] += $clamped;
                if ($clamped > 0) {
                    $skillPracticedDocs[$skillName]++;
                }
            }
        }

        return collect($defaultSkills)->map(function (string $desc, string $skillName) use ($skillTotals, $skillPracticedDocs, $docCount) {
            $avgPercent = $docCount > 0 ? min(100, (int) round($skillTotals[$skillName] / $docCount)) : 0;

            return (object) [
                'skill' => $skillName,
                'practiced_count' => $skillPracticedDocs[$skillName],
                'target_count' => $docCount,
                'percent' => $avgPercent,
                'description' => $desc,
            ];
        })->values();
    }
}
