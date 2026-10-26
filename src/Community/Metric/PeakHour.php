<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\DateHistogram;

class PeakHour implements CommunityMetric
{
    public function __construct(
        protected ContentVisibility $visibility,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return 'peak_hour';
    }

    public function calculate(int $year): array
    {
        $hourCounts = DateHistogram::counts($this->visibility->posts()
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00'), 'hour');

        $peakCount = max($hourCounts);

        if ($peakCount === 0) {
            return [
                'peak_hour' => null,
                'peak_count' => 0,
                'hour_counts' => $hourCounts,
            ];
        }

        $sorted = $hourCounts;
        arsort($sorted);
        $peakHour = array_key_first($sorted);

        return [
            'peak_hour' => $peakHour,
            'peak_count' => $peakCount,
            'hour_counts' => $hourCounts,
        ];
    }
}
