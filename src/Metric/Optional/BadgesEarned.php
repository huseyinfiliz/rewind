<?php

namespace HuseyinFiliz\Rewind\Metric\Optional;

use Flarum\User\User;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class BadgesEarned implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
    ) {
    }

    public function key(): string
    {
        return 'badges_earned';
    }

    public function requiredExtension(): ?string
    {
        return 'fof-badges';
    }

    public function calculate(User $user, int $year): array
    {
        $count = $this->db->table('fof_badge_user')
            ->where('fof_badge_user.user_id', $user->id)
            ->where('fof_badge_user.earned_at', '>=', "$year-01-01 00:00:00")
            ->where('fof_badge_user.earned_at', '<', ($year + 1).'-01-01 00:00:00')
            ->count();

        $badges = $this->db->table('fof_badge_user')
            ->join('fof_badges', 'fof_badges.id', '=', 'fof_badge_user.badge_id')
            ->where('fof_badge_user.user_id', $user->id)
            ->where('fof_badge_user.earned_at', '>=', "$year-01-01 00:00:00")
            ->where('fof_badge_user.earned_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('fof_badges.name', 'fof_badges.icon')
            ->limit(3)
            ->get();

        return [
            'count' => $count,
            'badges' => $badges->map(fn ($b) => [
                'name' => $b->name,
                'icon' => $b->icon,
            ])->toArray(),
        ];
    }
}
