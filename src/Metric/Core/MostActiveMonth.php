<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\DateHistogram;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class MostActiveMonth implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function key(): string
    {
        return 'most_active_month';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $months = DateHistogram::counts($this->visibility->posts()->where('user_id', $user->id)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00'), 'month');
        $peakMonth = 0;
        $peakCount = 0;

        for ($i = 1; $i <= 12; $i++) {
            $count = $months[$i];
            if ($count > $peakCount) {
                $peakCount = $count;
                $peakMonth = $i;
            }
        }

        return [
            'months' => $months,
            'peak_month' => $peakMonth,
            'peak_count' => $peakCount,
        ];
    }
}
