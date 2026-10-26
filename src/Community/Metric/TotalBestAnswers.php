<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class TotalBestAnswers implements CommunityMetric
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
        return 'total_best_answers';
    }

    public function calculate(int $year): array
    {
        $count = $this->db->table('discussions')
            ->whereIn('discussions.id', $this->visibility->discussions()->select('discussions.id'))
            ->whereIn('best_answer_post_id', $this->visibility->posts()->select('posts.id'))
            ->whereNotNull('best_answer_post_id')
            ->where('best_answer_set_at', '>=', "$year-01-01 00:00:00")
            ->where('best_answer_set_at', '<', ($year + 1).'-01-01 00:00:00')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->count();

        return ['count' => $count];
    }
}
