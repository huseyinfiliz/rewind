<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\Extension\ExtensionManager;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class BestPost implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ExtensionManager $extensions,
        protected ContentVisibility $visibility,
        protected ?Formatter $formatter = null,
    ) {
    }

    public function key(): string
    {
        return 'best_post';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $hasLikes = $this->extensions->isEnabled('flarum-likes');

        if ($hasLikes) {
            return $this->bestByLikes($user, $year);
        }

        return $this->bestByReplies($user, $year);
    }

    protected function bestByLikes(User $user, int $year): array
    {
        $prefix = $this->db->getTablePrefix();

        $result = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->leftJoin('post_likes', 'posts.id', '=', 'post_likes.post_id')
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('posts.id', 'posts.discussion_id', 'posts.content', 'discussions.title as discussion_title')
            ->selectRaw('COUNT('.$prefix.'post_likes.user_id) as like_count')
            ->groupBy('posts.id', 'posts.discussion_id', 'posts.content', 'discussions.title')
            ->orderByDesc('like_count')
            ->first();

        if (! $result || $result->like_count == 0) {
            return $this->bestByReplies($user, $year);
        }

        return [
            'post_id' => (int) $result->id,
            'discussion_id' => (int) $result->discussion_id,
            'discussion_title' => $result->discussion_title,
            'metric_type' => 'likes',
            'count' => (int) $result->like_count,
            'excerpt' => \HuseyinFiliz\Rewind\ContentCleaner::excerpt($result->content ?? ''),
            'content_html' => $this->renderContent($result->content ?? ''),
        ];
    }

    protected function bestByReplies(User $user, int $year): array
    {
        $result = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('posts.id', 'posts.discussion_id', 'posts.content', 'discussions.title as discussion_title', 'discussions.comment_count')
            ->orderByDesc('discussions.comment_count')
            ->first();

        if (! $result || $result->comment_count <= 1) {
            return [
                'post_id' => null,
                'discussion_id' => null,
                'discussion_title' => null,
                'metric_type' => null,
                'count' => 0,
                'excerpt' => null,
                'content_html' => null,
            ];
        }

        return [
            'post_id' => (int) $result->id,
            'discussion_id' => (int) $result->discussion_id,
            'discussion_title' => $result->discussion_title,
            'metric_type' => 'discussion_comments',
            'count' => (int) $result->comment_count,
            'excerpt' => \HuseyinFiliz\Rewind\ContentCleaner::excerpt($result->content ?? ''),
            'content_html' => $this->renderContent($result->content ?? ''),
        ];
    }

    protected function renderContent(string $content): string
    {
        try {
            if ($this->formatter) {
                return $this->formatter->render($content);
            }

            return htmlspecialchars(\HuseyinFiliz\Rewind\ContentCleaner::excerpt($content), ENT_QUOTES, 'UTF-8');
        } catch (\Throwable $e) {
            return htmlspecialchars(\HuseyinFiliz\Rewind\ContentCleaner::excerpt($content), ENT_QUOTES, 'UTF-8');
        }
    }
}
