<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class PostCount implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function key(): string
    {
        return 'post_count';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $count = $this->visibility->posts()->where('user_id', $user->id)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->count();

        return ['count' => $count];
    }
}
