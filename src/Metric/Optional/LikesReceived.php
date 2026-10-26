<?php

namespace HuseyinFiliz\Rewind\Metric\Optional;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class LikesReceived implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function key(): string
    {
        return 'likes_received';
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-likes';
    }

    public function calculate(User $user, int $year): array
    {
        $count = $this->db->table('post_likes')
            ->whereIn('post_likes.post_id', $this->visibility->posts()->select('posts.id'))
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->where('posts.user_id', $user->id)
            ->where('post_likes.created_at', '>=', "$year-01-01 00:00:00")
            ->where('post_likes.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->count();

        return ['count' => $count];
    }
}
