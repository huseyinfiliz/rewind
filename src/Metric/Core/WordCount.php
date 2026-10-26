<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class WordCount implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function key(): string
    {
        return 'word_count';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $totalWords = 0;

        $this->visibility->posts()->where('user_id', $user->id)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('id', 'content')
            ->chunkById(500, function ($posts) use (&$totalWords) {
                foreach ($posts as $post) {
                    $totalWords += \HuseyinFiliz\Rewind\ContentCleaner::countWords($post->content ?? '');
                }
            });

        return ['count' => $totalWords];
    }
}
