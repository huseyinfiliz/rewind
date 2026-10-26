<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class DiscussionCount implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function key(): string
    {
        return 'discussion_count';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $count = $this->visibility->discussions()->where('user_id', $user->id)
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->count();

        return ['count' => $count];
    }
}
