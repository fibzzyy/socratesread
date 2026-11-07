<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __invoke(GamificationService $gamification): View
    {
        $user = auth()->user();
        $summary = $gamification->summary($user);
        $weeklyActivity = $gamification->weeklyActivity($user);
        $recentActivities = $gamification->recentActivities($user, 8);
        $completedReadings = $user->learningActivities()->where('event_type', 'reading_completed')->count();
        $completedChallenges = $user->learningActivities()->where('event_type', 'challenge_completed')->count();
        $weeklyReadingCount = $user->learningActivities()
            ->where('event_type', 'reading_completed')
            ->whereBetween('activity_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
            ->count();
        $answersBySkill = $user->readingSessions()
            ->join('student_answers', 'student_answers.reading_session_id', '=', 'reading_sessions.id')
            ->join('socratic_questions', 'socratic_questions.id', '=', 'student_answers.socratic_question_id')
            ->selectRaw('socratic_questions.skill, COUNT(student_answers.id) as answer_count')
            ->groupBy('socratic_questions.skill')
            ->orderBy('socratic_questions.skill')
            ->get();

        return view('student.progress', compact(
            'summary', 'weeklyActivity', 'recentActivities', 'completedReadings',
            'completedChallenges', 'weeklyReadingCount', 'answersBySkill',
        ));
    }
}
