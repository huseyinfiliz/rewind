<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class TopContributors implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return 'top_contributors';
    }

    public function calculate(int $year): array
    {
        $rows = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->leftJoin('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->whereNotNull('posts.user_id')
            ->where('posts.user_id', '>', 0)
            ->select('posts.user_id', 'users.username')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('posts.user_id', 'users.username')
            ->orderByDesc('post_count')
            ->limit(5)
            ->get();

        return [
            'users' => $rows->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'username' => $r->username,
                'post_count' => (int) $r->post_count,
            ])->toArray(),
        ];
    }
}
