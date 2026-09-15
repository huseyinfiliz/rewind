<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use DateTimeInterface;
use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class PeakHour implements CommunityMetric
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
        return 'peak_hour';
    }

    public function calculate(int $year): array
    {
        $dates = $this->db->table('posts')
            ->where('type', 'comment')
            ->whereYear('created_at', $year)
            ->pluck('created_at');

        $hourCounts = array_fill(0, 24, 0);
        foreach ($dates as $date) {
            if ($date instanceof DateTimeInterface) {
                $postYear = (int) $date->format('Y');
                $hour = (int) $date->format('G');
            } else {
                $timestamp = strtotime((string) $date);
                if ($timestamp === false) {
                    continue;
                }
                $postYear = (int) date('Y', $timestamp);
                $hour = (int) date('G', $timestamp);
            }

            if ($postYear === $year && $hour >= 0 && $hour <= 23) {
                $hourCounts[$hour]++;
            }
        }

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
