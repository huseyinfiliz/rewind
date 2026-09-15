<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class BestPost implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ExtensionManager $extensions,
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
            ->leftJoin('post_likes', 'posts.id', '=', 'post_likes.post_id')
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->whereYear('posts.created_at', $year)
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
            'content_html' => $this->renderContent($result->content ?? ''),
        ];
    }

    protected function bestByReplies(User $user, int $year): array
    {
        $result = $this->db->table('posts')
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->whereYear('posts.created_at', $year)
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
                'content_html' => null,
            ];
        }

        return [
            'post_id' => (int) $result->id,
            'discussion_id' => (int) $result->discussion_id,
            'discussion_title' => $result->discussion_title,
            'metric_type' => 'discussion_comments',
            'count' => (int) $result->comment_count,
            'content_html' => $this->renderContent($result->content ?? ''),
        ];
    }

    protected function renderContent(string $content): string
    {
        try {
            $formatter = resolve(\Flarum\Formatter\Formatter::class);

            return $formatter->render($content);
        } catch (\Throwable $e) {
            return \HuseyinFiliz\Rewind\ContentCleaner::excerpt($content);
        }
    }
}
