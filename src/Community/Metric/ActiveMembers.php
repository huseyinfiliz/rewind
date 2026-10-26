<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;

class ActiveMembers implements CommunityMetric
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
        return 'active_members';
    }

    public function calculate(int $year): array
    {
        return ['count' => $this->visibility->posts()
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->where('user_id', '>', 0)->distinct()->count('user_id')];
    }
}
