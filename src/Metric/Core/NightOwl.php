<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\DateHistogram;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class NightOwl implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function key(): string
    {
        return 'night_owl';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $hourCounts = DateHistogram::counts($this->visibility->posts()->where('user_id', $user->id)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00'), 'hour');

        $peakCount = max($hourCounts);

        if ($peakCount === 0) {
            return [
                'peak_hour' => null,
                'count' => 0,
                'is_night_owl' => false,
                'hour_counts' => $hourCounts,
            ];
        }

        $sorted = $hourCounts;
        arsort($sorted);
        $peakHour = array_key_first($sorted);

        // 22:00 (10 PM) ve 05:00 (5 AM) arası "Night Owl" kabul edelim
        $isNightOwl = ($peakHour >= 22 || $peakHour <= 5);

        return [
            'peak_hour' => $peakHour,
            'count' => $peakCount,
            'is_night_owl' => $isNightOwl,
            'hour_counts' => $hourCounts,
        ];
    }
}
