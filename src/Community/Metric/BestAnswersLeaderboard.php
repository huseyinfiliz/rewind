<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class BestAnswersLeaderboard implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return 'fof-best-answer';
    }

    public function key(): string
    {
        return 'best_answers_leaderboard';
    }

    public function calculate(int $year): array
    {
        $users = $this->db->table('discussions')
            ->whereIn('discussions.best_answer_post_id', $this->visibility->posts()->select('posts.id'))
            ->join('posts', 'posts.id', '=', 'discussions.best_answer_post_id')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->whereNotNull('discussions.best_answer_post_id')
            ->where('discussions.best_answer_set_at', '>=', "$year-01-01 00:00:00")
            ->where('discussions.best_answer_set_at', '<', ($year + 1).'-01-01 00:00:00')
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->select('posts.user_id', 'users.username')
            ->selectRaw('COUNT(*) as answer_count')
            ->groupBy('posts.user_id', 'users.username')
            ->orderByDesc('answer_count')
            ->limit(5)
            ->get();

        return [
            'users' => $users->map(fn ($u) => [
                'user_id' => (int) $u->user_id,
                'username' => $u->username,
                'answer_count' => (int) $u->answer_count,
            ])->toArray(),
        ];
    }
}
