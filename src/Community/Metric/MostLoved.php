<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class MostLoved implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-likes';
    }

    public function key(): string
    {
        return 'most_loved';
    }

    public function calculate(int $year): array
    {
        $users = $this->db->table('post_likes')
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.type', 'comment')
            ->whereYear('post_likes.created_at', $year)
            ->select('posts.user_id', 'users.username')
            ->selectRaw('COUNT(*) as like_count')
            ->groupBy('posts.user_id', 'users.username')
            ->orderByDesc('like_count')
            ->limit(5)
            ->get();

        return [
            'users' => $users->map(fn ($u) => [
                'user_id' => (int) $u->user_id,
                'username' => $u->username,
                'like_count' => (int) $u->like_count,
            ])->toArray(),
        ];
    }
}
