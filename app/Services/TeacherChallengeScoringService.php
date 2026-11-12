<?php

namespace App\Services;

use App\Models\TeacherChallenge;
use Illuminate\Support\Collection;

class TeacherChallengeScoringService
{
    public function weightForDifficulty(?string $difficulty): int
    {
        return TeacherChallenge::weightForDifficulty($difficulty);
    }

    /**
     * Menghitung bobot poin maksimal tiap soal (total selalu tepat 100.00 poin)
     * secara proporsional berdasarkan level kesulitan (Easy=1, Medium=2, Hard=3).
     *
     * @param Collection<int, mixed>|array<int, mixed> $challenges
     * @return array<int|string, float> Map index/id => points_max
     */
    public function distributePoints(Collection|array $challenges): array
    {
        $items = $challenges instanceof Collection ? $challenges->values()->all() : array_values($challenges);
        $count = count($items);
        if ($count === 0) {
            return [];
        }

        $weights = [];
        $totalWeight = 0;
        foreach ($items as $idx => $item) {
            $key = is_object($item) && isset($item->id) ? $item->id : (is_array($item) && isset($item['id']) ? $item['id'] : $idx);
            $diff = is_object($item) ? ($item->difficulty ?? 'medium') : ($item['difficulty'] ?? 'medium');
            $explicitWeight = is_object($item) ? ($item->difficulty_weight ?? null) : ($item['difficulty_weight'] ?? null);
            $w = $explicitWeight ? max(1, min(3, (int) $explicitWeight)) : $this->weightForDifficulty($diff);
            $weights[$key] = $w;
            $totalWeight += $w;
        }

        $pointsMap = [];
        $runningSum = 0.0;
        $keys = array_keys($weights);
        $lastIdx = count($keys) - 1;

        foreach ($keys as $pos => $key) {
            if ($pos === $lastIdx) {
                $pointsMap[$key] = round(max(0.0, 100.0 - $runningSum), 2);
            } else {
                $pts = round(($weights[$key] / max(1, $totalWeight)) * 100.0, 2);
                $pointsMap[$key] = $pts;
                $runningSum += $pts;
            }
        }

        return $pointsMap;
    }

    /**
     * Menentukan reward XP maksimal oleh Socrates berdasarkan jumlah soal dan komposisi kesulitan.
     *
     * @param Collection<int, mixed>|array<int, mixed> $challenges
     */
    public function calculateMaxRewardXp(Collection|array $challenges): int
    {
        $items = $challenges instanceof Collection ? $challenges->values()->all() : array_values($challenges);
        $count = count($items);
        if ($count === 0) {
            return 0;
        }

        $xp = 15;
        foreach ($items as $item) {
            $diff = strtolower((string) (is_object($item) ? ($item->difficulty ?? 'medium') : ($item['difficulty'] ?? 'medium')));
            $xp += match ($diff) {
                'easy' => 3,
                'hard' => 8,
                default => 5,
            };
        }

        return min(75, max(20, $xp));
    }

    /**
     * Menghitung XP yang diperoleh siswa berdasarkan skor akhir (0..100) dan maksimal XP tantangan.
     */
    public function calculateEarnedXp(int $maxRewardXp, int $finalScore): int
    {
        if ($maxRewardXp <= 0) {
            return 0;
        }

        if ($finalScore <= 0) {
            return 5; // XP partisipasi minimal
        }

        return max(5, (int) round($maxRewardXp * ($finalScore / 100)));
    }
}
