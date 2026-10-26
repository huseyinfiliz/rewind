<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use Flarum\User\User;
use HuseyinFiliz\Rewind\Community\CommunityMetric;

class NewMembersList implements CommunityMetric
{
    public function requiredExtension(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return 'new_members_list';
    }

    public function calculate(int $year): array
    {
        $count = User::where('joined_at', '>=', "$year-01-01 00:00:00")
            ->where('joined_at', '<', ($year + 1).'-01-01 00:00:00')->count();

        $recent = User::where('joined_at', '>=', "$year-01-01 00:00:00")
            ->where('joined_at', '<', ($year + 1).'-01-01 00:00:00')
            ->orderByDesc('joined_at')
            ->limit(12)
            ->get(['id', 'username']);

        return [
            'count' => $count,
            'recent' => $recent->map(fn ($u) => [
                'user_id' => (int) $u->id,
                'username' => $u->username,
            ])->toArray(),
        ];
    }
}
