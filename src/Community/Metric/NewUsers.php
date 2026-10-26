<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use Flarum\User\User;
use HuseyinFiliz\Rewind\Community\CommunityMetric;

class NewUsers implements CommunityMetric
{
    public function requiredExtension(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return 'new_users';
    }

    public function calculate(int $year): array
    {
        $count = User::where('joined_at', '>=', "$year-01-01 00:00:00")
            ->where('joined_at', '<', ($year + 1).'-01-01 00:00:00')->count();

        return ['count' => $count];
    }
}
