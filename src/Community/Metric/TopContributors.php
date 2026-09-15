<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class TopContributors implements CommunityMetric
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
        return 'top_contributors';
    }

    public function calculate(int $year): array
    {
        $prefix = $this->db->getTablePrefix();

        $rows = $this->db->table('posts')
            ->leftJoin('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.type', 'comment')
            ->whereYear('posts.created_at', $year)
            ->whereNotNull('posts.user_id')
            ->where('posts.user_id', '>', 0)
            ->select('posts.user_id', 'users.username')
            ->selectRaw('COUNT('.$prefix.'posts.id) as post_count')
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
