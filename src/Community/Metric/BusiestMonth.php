<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use DateTimeInterface;
use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class BusiestMonth implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
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
        $dates = $this->db->table('posts')
            ->where('type', 'comment')
            ->whereYear('created_at', $year)
            ->pluck('created_at');

        $monthCounts = array_fill(1, 12, 0);
        foreach ($dates as $date) {
            if ($date instanceof DateTimeInterface) {
                $postYear = (int) $date->format('Y');
                $month = (int) $date->format('n');
            } else {
                $timestamp = strtotime((string) $date);
                if ($timestamp === false) {
                    continue;
                }
                $postYear = (int) date('Y', $timestamp);
                $month = (int) date('n', $timestamp);
            }

            if ($postYear === $year && $month >= 1 && $month <= 12) {
                $monthCounts[$month]++;
            }
        }

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
