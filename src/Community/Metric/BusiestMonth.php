<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\DateHistogram;

class BusiestMonth implements CommunityMetric
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
        return 'busiest_month';
    }

    public function calculate(int $year): array
    {
        $monthCounts = DateHistogram::counts($this->visibility->posts()
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00'), 'month');

        $peakCount = max($monthCounts);

        if ($peakCount === 0) {
            return [
                'months' => $monthCounts,
                'peak_month' => 0,
                'peak_count' => 0,
            ];
        }

        $peakMonth = array_search($peakCount, $monthCounts);

        return [
            'months' => $monthCounts,
            'peak_month' => $peakMonth,
            'peak_count' => $peakCount,
        ];
    }
}
