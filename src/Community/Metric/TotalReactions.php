<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class TotalReactions implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return 'fof-reactions';
    }

    public function key(): string
    {
        return 'total_reactions';
    }

    public function calculate(int $year): array
    {
        $count = $this->db->table('post_reactions')
            ->whereIn('post_reactions.post_id', $this->visibility->posts()->select('posts.id'))
            ->join('posts', 'posts.id', '=', 'post_reactions.post_id')
            ->where('post_reactions.created_at', '>=', "$year-01-01 00:00:00")
            ->where('post_reactions.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->count();

        return ['count' => $count];
    }
}
