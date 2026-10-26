<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class MostActiveUser implements CommunityMetric
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
        return 'most_active_user';
    }

    public function calculate(int $year): array
    {
        $result = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('users.id', 'users.username')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('users.id', 'users.username')
            ->orderByDesc('post_count')
            ->first();

        if (! $result) {
            return ['id' => null, 'username' => null, 'post_count' => 0];
        }

        return [
            'id' => (int) $result->id,
            'username' => $result->username,
            'post_count' => (int) $result->post_count,
        ];
    }
}
