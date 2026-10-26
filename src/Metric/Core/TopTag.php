<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;
use Illuminate\Database\ConnectionInterface;

class TopTag implements RewindMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function key(): string
    {
        return 'top_tag';
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-tags';
    }

    public function calculate(User $user, int $year): array
    {
        $prefix = $this->db->getTablePrefix();

        $result = $this->db->table('posts')
            ->whereIn('posts.id', $this->visibility->posts()->select('posts.id'))
            ->join('discussions', 'posts.discussion_id', '=', 'discussions.id')
            ->join('discussion_tag', 'posts.discussion_id', '=', 'discussion_tag.discussion_id')
            ->join('tags', 'discussion_tag.tag_id', '=', 'tags.id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('posts.created_at', '>=', "$year-01-01 00:00:00")
            ->where('posts.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('tags.id', 'tags.name', 'tags.slug', 'tags.color', 'tags.icon')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('tags.id', 'tags.name', 'tags.slug', 'tags.color', 'tags.icon')
            ->orderByDesc('post_count')
            ->orderByDesc($this->db->raw('MAX('.$prefix.'posts.created_at)'))
            ->first();

        if (! $result) {
            return ['tag_name' => null, 'tag_slug' => null, 'tag_color' => null, 'tag_icon' => null, 'count' => 0];
        }

        return [
            'tag_name' => $result->name,
            'tag_slug' => $result->slug,
            'tag_color' => $result->color,
            'tag_icon' => $result->icon,
            'count' => (int) $result->post_count,
        ];
    }
}
