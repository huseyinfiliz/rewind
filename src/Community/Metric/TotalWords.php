<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;

class TotalWords implements CommunityMetric
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
        return 'total_words';
    }

    public function calculate(int $year): array
    {
        $totalWords = 0;
        $totalPosts = 0;

        $this->visibility->posts()->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('id', 'content')
            ->chunkById(1000, function ($posts) use (&$totalWords, &$totalPosts) {
                foreach ($posts as $post) {
                    $totalWords += \HuseyinFiliz\Rewind\ContentCleaner::countWords($post->content ?? '');
                    $totalPosts++;
                }
            });

        return [
            'total_words' => $totalWords,
            'total_posts' => $totalPosts,
            'avg_words_per_post' => $totalPosts > 0 ? round($totalWords / $totalPosts, 1) : 0,
        ];
    }

    /** @return array{last_id: int, words: int, posts: int, complete: bool} */
    public function batch(int $year, int $after): array
    {
        $posts = $this->visibility->posts()
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->where('posts.id', '>', $after)->orderBy('posts.id')
            ->limit(1000)->get(['posts.id', 'posts.content']);

        $words = 0;
        foreach ($posts as $post) {
            $words += \HuseyinFiliz\Rewind\ContentCleaner::countWords($post->content ?? '');
        }

        return [
            'last_id' => $posts->last()->id ?? $after,
            'words' => $words,
            'posts' => $posts->count(),
            'complete' => $posts->count() < 1000,
        ];
    }
}
