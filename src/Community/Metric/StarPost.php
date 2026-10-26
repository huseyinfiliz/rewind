<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use Flarum\Formatter\Formatter;
use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class StarPost implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
        protected ?Formatter $formatter = null,
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
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->leftJoin('post_likes', 'posts.id', '=', 'post_likes.post_id')
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('posts.id', 'posts.user_id', 'posts.content', 'posts.discussion_id', 'discussions.title as discussion_title', 'users.username')
            ->selectRaw('COUNT('.$prefix.'post_likes.user_id) as like_count')
            ->groupBy('posts.id', 'posts.user_id', 'posts.content', 'posts.discussion_id', 'discussions.title', 'users.username')
            ->orderByDesc('like_count')
            ->first();

        if (! $result || $result->like_count == 0) {
            return $this->empty();
        }

        try {
            $contentHtml = $this->formatter ? $this->formatter->render($result->content ?? '') : htmlspecialchars(\HuseyinFiliz\Rewind\ContentCleaner::excerpt($result->content ?? ''), ENT_QUOTES, 'UTF-8');
        } catch (\Throwable $e) {
            $contentHtml = htmlspecialchars(\HuseyinFiliz\Rewind\ContentCleaner::excerpt($result->content ?? ''), ENT_QUOTES, 'UTF-8');
        }

        return [
            'post_id' => (int) $result->id,
            'user_id' => (int) $result->user_id,
            'username' => $result->username,
            'discussion_id' => (int) $result->discussion_id,
            'discussion_title' => $result->discussion_title,
            'like_count' => (int) $result->like_count,
            'excerpt' => \HuseyinFiliz\Rewind\ContentCleaner::excerpt($result->content ?? ''),
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
            'excerpt' => null,
            'content_html' => null,
        ];
    }
}
