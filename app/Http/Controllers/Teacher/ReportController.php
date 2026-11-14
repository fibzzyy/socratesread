<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\StudentQuizAttempt;
use App\Models\StudentTeacherChallengeSubmission;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user();
        $reportData = $this->buildReportData($request, $teacher);

        return view('teacher.reports', $reportData);
    }

    public function exportExcel(Request $request): Response
    {
        $teacher = $request->user();
        $reportData = $this->buildReportData($request, $teacher);

        $className = $reportData['selectedClass']
            ? preg_replace('/[^A-Za-z0-9_-]/', '_', $reportData['selectedClass']->name)
            : 'Semua_Kelas';

        $categoryName = ($reportData['selectedCategory'] && $reportData['selectedCategory'] !== 'all')
            ? preg_replace('/[^A-Za-z0-9_-]/', '_', $reportData['selectedCategory'])
            : 'Semua_Mapel';

        $assessmentLabel = match ($reportData['assessmentType']) {
            'quiz' => 'Kuis_Socrates',
            'combined' => 'Nilai_Gabungan',
            default => 'Tantangan_Guru',
        };

        $fileName = 'Rekap_Nilai_' . $className . '_' . $categoryName . '_' . $assessmentLabel . '_' . now()->format('Ymd_His') . '.xls';

        $content = view('teacher.reports-excel', $reportData)->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    private function buildReportData(Request $request, User $teacher): array
    {
        // 1. Ambil seluruh kelas yang diampu guru ini
        $classes = $teacher->teacherClasses()->orderBy('name')->get();

        // 2. Tentukan kelas yang dipilih (default: kelas pertama jika ada)
        $selectedClassId = $request->query('class_id');
        $selectedClass = null;
        if ($selectedClassId) {
            $selectedClass = $classes->firstWhere('id', (int) $selectedClassId);
        } elseif ($classes->isNotEmpty()) {
            $selectedClass = $classes->first();
            $selectedClassId = $selectedClass->id;
        }

        // 3. Ambil daftar kategori/mapel dari materi bacaan yang pernah diunggah guru
        $categories = Document::where('uploaded_by', $teacher->id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        $selectedCategory = $request->query('category', 'all');

        // 4. Tipe penilaian: 'teacher_challenge' (default), 'quiz', atau 'combined'
        $assessmentType = $request->query('type', 'teacher_challenge');
        if (! in_array($assessmentType, ['teacher_challenge', 'quiz', 'combined'], true)) {
            $assessmentType = 'teacher_challenge';
        }

        // 5. Ambil data siswa pada kelas terpilih
        $students = collect();
        if ($selectedClass) {
            $students = $selectedClass->students()
                ->select('users.id', 'users.name', 'users.nisn', 'users.email', 'users.school_origin')
                ->orderBy('users.name')
                ->get();
        }

        // 6. Ambil dokumen materi bacaan guru (berdasarkan kategori dan target jenjang jika relevan)
        $docsQuery = Document::query()->where('uploaded_by', $teacher->id);

        if ($selectedCategory && $selectedCategory !== 'all') {
            $docsQuery->where('category', $selectedCategory);
        }

        if ($selectedClass && $selectedClass->target_grade) {
            $docsQuery->where(function ($q) use ($selectedClass) {
                $q->whereNull('target_grade')
                  ->orWhere('target_grade', 0)
                  ->orWhere('target_grade', $selectedClass->target_grade);
            });
        }

        $documents = $docsQuery->orderBy('id', 'asc')->get();

        $studentIds = $students->pluck('id')->all();
        $documentIds = $documents->pluck('id')->all();

        // 7. Bentuk matriks nilai siswa: [student_id => [document_id => score]]
        $scoreMatrix = [];
        foreach ($studentIds as $sId) {
            $scoreMatrix[$sId] = [];
            foreach ($documentIds as $dId) {
                $scoreMatrix[$sId][$dId] = null;
            }
        }

        if (! empty($studentIds) && ! empty($documentIds)) {
            if ($assessmentType === 'teacher_challenge') {
                $submissions = StudentTeacherChallengeSubmission::query()
                    ->whereIn('user_id', $studentIds)
                    ->whereIn('document_id', $documentIds)
                    ->where('status', 'completed')
                    ->get();

                foreach ($submissions as $sub) {
                    $scoreMatrix[$sub->user_id][$sub->document_id] = (int) $sub->score;
                }
            } elseif ($assessmentType === 'quiz') {
                $attempts = StudentQuizAttempt::query()
                    ->whereIn('user_id', $studentIds)
                    ->whereIn('document_id', $documentIds)
                    ->where('status', 'completed')
                    ->select('user_id', 'document_id', DB::raw('MAX(score) as best_score'))
                    ->groupBy('user_id', 'document_id')
                    ->get();

                foreach ($attempts as $att) {
                    $scoreMatrix[$att->user_id][$att->document_id] = (int) $att->best_score;
                }
            } elseif ($assessmentType === 'combined') {
                $challengeSubmissions = StudentTeacherChallengeSubmission::query()
                    ->whereIn('user_id', $studentIds)
                    ->whereIn('document_id', $documentIds)
                    ->where('status', 'completed')
                    ->get()
                    ->keyBy(fn ($item) => "{$item->user_id}_{$item->document_id}");

                $quizAttempts = StudentQuizAttempt::query()
                    ->whereIn('user_id', $studentIds)
                    ->whereIn('document_id', $documentIds)
                    ->where('status', 'completed')
                    ->select('user_id', 'document_id', DB::raw('MAX(score) as best_score'))
                    ->groupBy('user_id', 'document_id')
                    ->get()
                    ->keyBy(fn ($item) => "{$item->user_id}_{$item->document_id}");

                foreach ($studentIds as $sId) {
                    foreach ($documentIds as $dId) {
                        $key = "{$sId}_{$dId}";
                        $hasChal = isset($challengeSubmissions[$key]);
                        $hasQuiz = isset($quizAttempts[$key]);

                        if ($hasChal && $hasQuiz) {
                            $scoreMatrix[$sId][$dId] = (int) round(($challengeSubmissions[$key]->score + $quizAttempts[$key]->best_score) / 2);
                        } elseif ($hasChal) {
                            $scoreMatrix[$sId][$dId] = (int) $challengeSubmissions[$key]->score;
                        } elseif ($hasQuiz) {
                            $scoreMatrix[$sId][$dId] = (int) $quizAttempts[$key]->best_score;
                        }
                    }
                }
            }
        }

        // 8. Hitung statistik nilai tiap siswa & akumulator materi
        $studentReports = [];
        $totalClassScore = 0;
        $totalClassCount = 0;
        $passedStudentsCount = 0;
        $studentsWithScoresCount = 0;
        $totalRequiredSubmissions = count($studentIds) * count($documentIds);
        $actualSubmissionsCount = 0;

        $docScoreSums = array_fill_keys($documentIds, 0);
        $docScoreCounts = array_fill_keys($documentIds, 0);

        foreach ($students as $student) {
            $scores = $scoreMatrix[$student->id] ?? [];
            $completedScores = array_filter($scores, fn ($v) => $v !== null);
            $completedCount = count($completedScores);
            $actualSubmissionsCount += $completedCount;

            $avgScore = null;
            $predicate = '—';
            $status = 'Belum Ada Nilai';

            if ($completedCount > 0) {
                $sum = array_sum($completedScores);
                $avgScore = round($sum / $completedCount, 1);
                $totalClassScore += $avgScore;
                $totalClassCount++;
                $studentsWithScoresCount++;

                if ($avgScore >= 88) {
                    $predicate = 'A';
                } elseif ($avgScore >= 75) {
                    $predicate = 'B';
                } elseif ($avgScore >= 60) {
                    $predicate = 'C';
                } else {
                    $predicate = 'D';
                }

                if ($avgScore >= 75) {
                    $status = 'Tuntas';
                    $passedStudentsCount++;
                } else {
                    $status = 'Perlu Bimbingan';
                }
            }

            foreach ($scores as $dId => $score) {
                if ($score !== null) {
                    $docScoreSums[$dId] += $score;
                    $docScoreCounts[$dId]++;
                }
            }

            $studentReports[] = [
                'student' => $student,
                'scores' => $scores,
                'completed_count' => $completedCount,
                'average_score' => $avgScore,
                'predicate' => $predicate,
                'status' => $status,
            ];
        }

        // 9. Statistik per kolom dokumen/materi
        $docStats = [];
        foreach ($documents as $doc) {
            $c = $docScoreCounts[$doc->id] ?? 0;
            $s = $docScoreSums[$doc->id] ?? 0;
            $docStats[$doc->id] = [
                'average' => $c > 0 ? round($s / $c, 1) : null,
                'completed_count' => $c,
            ];
        }

        // 10. Indikator Kinerja Utama (KPI) kelas
        $classAverage = $totalClassCount > 0 ? round($totalClassScore / $totalClassCount, 1) : 0;
        $passRate = $studentsWithScoresCount > 0 ? round(($passedStudentsCount / $studentsWithScoresCount) * 100) : 0;
        $pendingSubmissions = max(0, $totalRequiredSubmissions - $actualSubmissionsCount);

        return [
            'teacher' => $teacher,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'selectedClassId' => $selectedClassId,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'assessmentType' => $assessmentType,
            'documents' => $documents,
            'students' => $students,
            'studentReports' => $studentReports,
            'docStats' => $docStats,
            'classAverage' => $classAverage,
            'passRate' => $passRate,
            'passedStudentsCount' => $passedStudentsCount,
            'totalStudentsCount' => $students->count(),
            'pendingSubmissions' => $pendingSubmissions,
            'kkm' => 75,
        ];
    }
}
