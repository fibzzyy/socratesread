<?php

namespace App\Services;

use App\Models\ReadingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    public function awardOnce(ReadingSession $session, string $eventType, int $xp, string $summary): int
    {
        return DB::transaction(function () use ($session, $eventType, $xp, $summary): int {
            $session->user()->lockForUpdate()->first();

            $hasEarnedForDocument = DB::table('learning_activities')
                ->where('user_id', $session->user_id)
                ->where('document_id', $session->document_id)
                ->where('event_type', $eventType)
                ->where('xp_awarded', '>', 0)
                ->exists();
            $xpToAward = $hasEarnedForDocument ? 0 : $xp;

            $inserted = DB::table('learning_activities')->insertOrIgnore([
                'user_id' => $session->user_id,
                'reading_session_id' => $session->id,
                'document_id' => $session->document_id,
                'event_type' => $eventType,
                'xp_awarded' => $xpToAward,
                'summary' => $summary,
                'activity_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $inserted === 1 ? $xpToAward : 0;
        });
    }

    public function awardQuiz(\App\Models\StudentQuizAttempt $attempt, int $score): int
    {
        return DB::transaction(function () use ($attempt, $score): int {
            $user = User::query()->whereKey($attempt->user_id)->lockForUpdate()->first();
            if (! $user) {
                return 0;
            }

            // Aturan XP Kuis: 100 => 30 XP, >=70 => 25 XP, >=50 => 15 XP, >0 => 10 XP, 0 => 5 XP (partisipasi)
            $earnedXp = match (true) {
                $score === 100 => 30,
                $score >= 70 => 25,
                $score >= 50 => 15,
                $score > 0 => 10,
                default => 5,
            };

            $session = ReadingSession::query()
                ->where('user_id', $attempt->user_id)
                ->where('document_id', $attempt->document_id)
                ->latest('id')
                ->first();

            if (! $session) {
                $session = ReadingSession::query()->create([
                    'user_id' => $attempt->user_id,
                    'document_id' => $attempt->document_id,
                    'status' => 'in_progress',
                    'progress_percent' => 0,
                    'started_at' => now(),
                ]);
            }

            $summary = $attempt->mode === 'timed_quiz'
                ? "Menyelesaikan Kuis Timer Pilihan Ganda (Skor {$score}/100)"
                : "Menyelesaikan Latihan Pilihan Ganda (Skor {$score}/100)";

            $eventType = 'quiz_' . $attempt->mode;

            $existing = DB::table('learning_activities')
                ->where('user_id', $attempt->user_id)
                ->where('reading_session_id', $session->id)
                ->where('event_type', $eventType)
                ->first();

            if ($existing) {
                if ($existing->xp_awarded < $earnedXp) {
                    DB::table('learning_activities')->where('id', $existing->id)->update([
                        'xp_awarded' => $earnedXp,
                        'summary' => $summary,
                        'updated_at' => now(),
                    ]);
                }
                return (int) max($existing->xp_awarded, $earnedXp);
            }

            DB::table('learning_activities')->insert([
                'user_id' => $attempt->user_id,
                'reading_session_id' => $session->id,
                'document_id' => $attempt->document_id,
                'event_type' => $eventType,
                'xp_awarded' => $earnedXp,
                'summary' => $summary,
                'activity_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $earnedXp;
        });
    }

    /** @return array{xp: int, level: int, xp_into_level: int, xp_to_next_level: int, level_progress: int, current_streak: int, longest_streak: int} */
    public function summary(User $user, ?array $documentIds = null): array
    {
        $activitiesQuery = $user->learningActivities()
            ->when($documentIds !== null, fn ($q) => $q->whereIn('document_id', $documentIds));

        $xp = (int) (clone $activitiesQuery)->sum('xp_awarded');
        $dates = (clone $activitiesQuery)
            ->select('activity_date')
            ->distinct()
            ->orderByDesc('activity_date')
            ->pluck('activity_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->startOfDay());

        $currentStreak = $this->currentStreak($dates);
        $longestStreak = $this->longestStreak($dates);
        $xpIntoLevel = $xp % 100;

        return [
            'xp' => $xp,
            'level' => intdiv($xp, 100) + 1,
            'xp_into_level' => $xpIntoLevel,
            'xp_to_next_level' => 100 - $xpIntoLevel,
            'level_progress' => $xpIntoLevel,
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
        ];
    }

    public function recentActivities(User $user, int $limit = 6, ?array $documentIds = null): Collection
    {
        return $user->learningActivities()
            ->when($documentIds !== null, fn ($q) => $q->whereIn('document_id', $documentIds))
            ->with('readingSession.document:id,title,category')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function weeklyActivity(User $user, ?array $documentIds = null): Collection
    {
        $start = now()->startOfDay()->subDays(6);
        $activities = $user->learningActivities()
            ->when($documentIds !== null, fn ($q) => $q->whereIn('document_id', $documentIds))
            ->whereBetween('activity_date', [$start->toDateString(), now()->toDateString()])
            ->get()
            ->groupBy(fn ($activity) => $activity->activity_date->toDateString());

        return collect(range(0, 6))->map(function (int $offset) use ($start, $activities) {
            $date = $start->copy()->addDays($offset);
            $items = $activities->get($date->toDateString(), collect());

            return [
                'date' => $date,
                'label' => $date->locale('id')->translatedFormat('D'),
                'xp' => (int) $items->sum('xp_awarded'),
                'events' => $items->count(),
            ];
        });
    }

    private function currentStreak(Collection $dates): int
    {
        if ($dates->isEmpty()) {
            return 0;
        }

        $today = CarbonImmutable::today();
        $latest = $dates->first();

        if (! $latest->isSameDay($today) && ! $latest->isSameDay($today->subDay())) {
            return 0;
        }

        $streak = 1;
        for ($index = 1; $index < $dates->count(); $index++) {
            if (! $dates[$index]->isSameDay($dates[$index - 1]->subDay())) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function longestStreak(Collection $dates): int
    {
        if ($dates->isEmpty()) {
            return 0;
        }

        $ascending = $dates->sort()->values();
        $longest = 1;
        $streak = 1;

        for ($index = 1; $index < $ascending->count(); $index++) {
            if ($ascending[$index - 1]->addDay()->isSameDay($ascending[$index])) {
                $streak++;
                $longest = max($longest, $streak);
            } else {
                $streak = 1;
            }
        }

        return $longest;
    }
}
