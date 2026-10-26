<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;

class TotalPosts implements CommunityMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return 'total_posts';
    }

    public function calculate(int $year): array
    {
        $count = $this->visibility->posts()->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->count();

        return ['count' => $count];
    }
}
