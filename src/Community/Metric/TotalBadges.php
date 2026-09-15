<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class TotalBadges implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return 'fof-badges';
    }

    public function key(): string
    {
        return 'total_badges';
    }

    public function calculate(int $year): array
    {
        $count = $this->db->table('fof_badge_user')
            ->whereYear('earned_at', $year)
            ->count();

        return ['count' => $count];
    }
}
