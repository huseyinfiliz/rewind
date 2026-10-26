<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class MostLoved implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
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
            ->whereIn('post_likes.post_id', $this->visibility->posts()->select('posts.id'))
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->where('post_likes.created_at', '>=', "$year-01-01 00:00:00")
            ->where('post_likes.created_at', '<', ($year + 1).'-01-01 00:00:00')
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
