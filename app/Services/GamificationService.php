<?php

namespace App\Services;

use App\Models\ReadingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GamificationService
{
    public function awardOnce(ReadingSession $session, string $eventType, int $xp, string $summary): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($session, $eventType, $xp, $summary): int {
            $session->user()->lockForUpdate()->first();

            $hasEarnedForDocument = \Illuminate\Support\Facades\DB::table('learning_activities')
                ->where('user_id', $session->user_id)
                ->where('document_id', $session->document_id)
                ->where('event_type', $eventType)
                ->where('xp_awarded', '>', 0)
                ->exists();
            $xpToAward = $hasEarnedForDocument ? 0 : $xp;

            $inserted = \Illuminate\Support\Facades\DB::table('learning_activities')->insertOrIgnore([
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

    /** @return array{xp: int, level: int, xp_into_level: int, xp_to_next_level: int, level_progress: int, current_streak: int, longest_streak: int} */
    public function summary(User $user, string $grade = 'all', ?array $documentIds = null): array
    {
        $activities = $this->activitiesForGrade($user, $grade, $documentIds);
        $xp = (int) (clone $activities)->sum('xp_awarded');
        $dates = (clone $activities)
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

    public function recentActivities(User $user, int $limit = 6, string $grade = 'all', ?array $documentIds = null): Collection
    {
        return $this->activitiesForGrade($user, $grade, $documentIds)
            ->with(['readingSession.document:id,title', 'document:id,title'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function weeklyActivity(User $user, string $grade = 'all', ?array $documentIds = null): Collection
    {
        $start = now()->startOfDay()->subDays(6);
        $activities = $this->activitiesForGrade($user, $grade, $documentIds)
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

    private function activitiesForGrade(User $user, string $grade, ?array $documentIds = null): HasMany
    {
        return $user->learningActivities()
            ->when($grade !== 'all', fn ($query) => $query->whereHas('document', fn ($documents) => $documents
                ->whereIn('target_grade', ['all', $grade])))
            ->when($documentIds !== null, fn ($query) => $query->whereIn('document_id', $documentIds));
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
