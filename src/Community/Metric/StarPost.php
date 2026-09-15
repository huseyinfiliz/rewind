<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class StarPost implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-likes';
    }

    public function key(): string
    {
        return 'star_post';
    }

    public function calculate(int $year): array
    {
        $prefix = $this->db->getTablePrefix();

        $result = $this->db->table('posts')
            ->leftJoin('post_likes', 'posts.id', '=', 'post_likes.post_id')
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.type', 'comment')
            ->whereYear('posts.created_at', $year)
            ->select('posts.id', 'posts.user_id', 'posts.content', 'posts.discussion_id', 'discussions.title as discussion_title', 'users.username')
            ->selectRaw('COUNT('.$prefix.'post_likes.user_id) as like_count')
            ->groupBy('posts.id', 'posts.user_id', 'posts.content', 'posts.discussion_id', 'discussions.title', 'users.username')
            ->orderByDesc('like_count')
            ->first();

        if (! $result || $result->like_count == 0) {
            return $this->empty();
        }

        try {
            $formatter = resolve(\Flarum\Formatter\Formatter::class);
            $contentHtml = $formatter->render($result->content ?? '');
        } catch (\Throwable $e) {
            $contentHtml = \HuseyinFiliz\Rewind\ContentCleaner::excerpt($result->content ?? '');
        }

        return [
            'post_id' => (int) $result->id,
            'user_id' => (int) $result->user_id,
            'username' => $result->username,
            'discussion_id' => (int) $result->discussion_id,
            'discussion_title' => $result->discussion_title,
            'like_count' => (int) $result->like_count,
            'content_html' => $contentHtml,
        ];
    }

    private function empty(): array
    {
        return [
            'post_id' => null,
            'user_id' => null,
            'username' => null,
            'discussion_id' => null,
            'discussion_title' => null,
            'like_count' => 0,
            'content_html' => null,
        ];
    }
}
