<?php

namespace HuseyinFiliz\Rewind\Metric\Optional;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class BestFriendMentions implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function key(): string
    {
        return 'best_friend_mentions';
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-mentions';
    }

    public function calculate(User $user, int $year): array
    {
        $mentioned = $this->db->table('post_mentions_user')
            ->whereIn('post_mentions_user.post_id', $this->visibility->posts()->select('posts.id'))
            ->join('posts', 'posts.id', '=', 'post_mentions_user.post_id')
            ->join('users', 'users.id', '=', 'post_mentions_user.mentions_user_id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->where('post_mentions_user.mentions_user_id', '!=', $user->id)
            ->where('post_mentions_user.created_at', '>=', "$year-01-01 00:00:00")
            ->where('post_mentions_user.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('post_mentions_user.mentions_user_id as user_id', 'users.username')
            ->selectRaw('COUNT(*) as mention_count')
            ->groupBy('post_mentions_user.mentions_user_id', 'users.username')
            ->orderByDesc('mention_count')
            ->first();

        $mentionedBy = $this->db->table('post_mentions_user')
            ->whereIn('post_mentions_user.post_id', $this->visibility->posts()->select('posts.id'))
            ->join('posts', 'posts.id', '=', 'post_mentions_user.post_id')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('post_mentions_user.mentions_user_id', $user->id)
            ->where('posts.user_id', '!=', $user->id)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->where('post_mentions_user.created_at', '>=', "$year-01-01 00:00:00")
            ->where('post_mentions_user.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('posts.user_id as user_id', 'users.username')
            ->selectRaw('COUNT(*) as mention_count')
            ->groupBy('posts.user_id', 'users.username')
            ->orderByDesc('mention_count')
            ->first();

        return [
            'user_id' => $mentioned ? (int) $mentioned->user_id : null,
            'username' => $mentioned?->username,
            'display_name' => $mentioned?->username,
            'mention_count' => $mentioned ? (int) $mentioned->mention_count : 0,
            'mentioned_by_user_id' => $mentionedBy ? (int) $mentionedBy->user_id : null,
            'mentioned_by_username' => $mentionedBy?->username,
            'mentioned_by_display_name' => $mentionedBy?->username,
            'mentioned_by_count' => $mentionedBy ? (int) $mentionedBy->mention_count : 0,
        ];
    }
}
