<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class BadgeLeaderboard implements CommunityMetric
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
        return 'badge_leaderboard';
    }

    public function calculate(int $year): array
    {
        $users = $this->db->table('fof_badge_user')
            ->join('users', 'users.id', '=', 'fof_badge_user.user_id')
            ->whereYear('fof_badge_user.earned_at', $year)
            ->select('fof_badge_user.user_id', 'users.username')
            ->selectRaw('COUNT(*) as badge_count')
            ->groupBy('fof_badge_user.user_id', 'users.username')
            ->orderByDesc('badge_count')
            ->limit(5)
            ->get();

        return [
            'users' => $users->map(fn ($u) => [
                'user_id' => (int) $u->user_id,
                'username' => $u->username,
                'badge_count' => (int) $u->badge_count,
            ])->toArray(),
        ];
    }
}
