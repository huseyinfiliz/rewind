<?php

namespace HuseyinFiliz\Rewind\Metric\Optional;

use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class BestFriend implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ExtensionManager $extensions,
    ) {
    }

    public function key(): string
    {
        return 'best_friend';
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-likes';
    }

    public function calculate(User $user, int $year): array
    {
        $result = $this->db->table('post_likes')
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->join('users', 'users.id', '=', 'post_likes.user_id')
            ->where('posts.user_id', $user->id)
            ->where('post_likes.user_id', '!=', $user->id)
            ->whereYear('post_likes.created_at', $year)
            ->select('post_likes.user_id', 'users.username')
            ->selectRaw('COUNT(*) as interaction_count')
            ->groupBy('post_likes.user_id', 'users.username')
            ->orderByDesc('interaction_count')
            ->first();

        if (! $result) {
            return $this->empty();
        }

        $friendId = (int) $result->user_id;
        $likesReceived = (int) $result->interaction_count;

        $likesGiven = $this->db->table('post_likes')
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->where('post_likes.user_id', $user->id)
            ->where('posts.user_id', $friendId)
            ->whereYear('post_likes.created_at', $year)
            ->count();

        $mentionsTo = 0;
        $mentionsFrom = 0;

        if ($this->extensions->isEnabled('flarum-mentions')) {
            $mentionsTo = (int) $this->db->table('post_mentions_user')
                ->join('posts', 'posts.id', '=', 'post_mentions_user.post_id')
                ->where('posts.user_id', $user->id)
                ->where('post_mentions_user.mentions_user_id', $friendId)
                ->whereYear('post_mentions_user.created_at', $year)
                ->count();

            $mentionsFrom = (int) $this->db->table('post_mentions_user')
                ->join('posts', 'posts.id', '=', 'post_mentions_user.post_id')
                ->where('posts.user_id', $friendId)
                ->where('post_mentions_user.mentions_user_id', $user->id)
                ->whereYear('post_mentions_user.created_at', $year)
                ->count();
        }

        return [
            'user_id' => $friendId,
            'username' => $result->username,
            'display_name' => $result->username,
            'interaction_count' => (int) $result->interaction_count,
            'likes_received' => $likesReceived,
            'likes_given' => $likesGiven,
            'mentions_to' => $mentionsTo,
            'mentions_from' => $mentionsFrom,
        ];
    }

    private function empty(): array
    {
        return [
            'user_id' => null,
            'username' => null,
            'display_name' => null,
            'interaction_count' => 0,
            'likes_received' => 0,
            'likes_given' => 0,
            'mentions_to' => 0,
            'mentions_from' => 0,
        ];
    }
}
