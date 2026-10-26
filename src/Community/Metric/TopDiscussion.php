<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use Flarum\Formatter\Formatter;
use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class TopDiscussion implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
        protected ?Formatter $formatter = null,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function key(): string
    {
        return 'top_discussion';
    }

    public function calculate(int $year): array
    {
        $prefix = $this->db->getTablePrefix();

        $result = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->join('discussions', 'discussions.id', '=', 'posts.discussion_id')
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('discussions.id', 'discussions.title', 'discussions.slug')
            ->selectRaw('COUNT('.$prefix.'posts.id) as post_count')
            ->groupBy('discussions.id', 'discussions.title', 'discussions.slug')
            ->orderByDesc('post_count')
            ->first();

        if (! $result) {
            return [
                'id' => null,
                'title' => null,
                'slug' => null,
                'post_count' => 0,
                'excerpt' => null,
                'content_html' => null,
                'author_username' => null,
                'author_id' => null,
            ];
        }

        $firstPost = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.discussion_id', $result->id)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->orderBy('posts.number')
            ->select('posts.content', 'users.username', 'users.id as user_id')
            ->first();

        $contentHtml = null;
        $excerpt = null;
        if ($firstPost && $firstPost->content) {
            $excerpt = \HuseyinFiliz\Rewind\ContentCleaner::excerpt($firstPost->content);

            try {
                $contentHtml = $this->formatter ? $this->formatter->render($firstPost->content) : htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8');
            } catch (\Throwable $e) {
                $contentHtml = htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8');
            }
        }

        return [
            'id' => (int) $result->id,
            'title' => $result->title,
            'slug' => $result->slug,
            'post_count' => (int) $result->post_count,
            'excerpt' => $excerpt,
            'content_html' => $contentHtml,
            'author_username' => $firstPost?->username,
            'author_id' => $firstPost ? (int) $firstPost->user_id : null,
        ];
    }
}
