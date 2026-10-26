<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use HuseyinFiliz\Rewind\ContentVisibility;
use Illuminate\Database\ConnectionInterface;

class CommunityTopTag implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ContentVisibility $visibility,
    ) {
    }

    public function requiredExtension(): ?string
    {
        return 'flarum-tags';
    }

    public function key(): string
    {
        return 'top_tag';
    }

    public function calculate(int $year): array
    {
        $prefix = $this->db->getTablePrefix();

        $result = $this->db->table('discussion_tag')
            ->whereIn('discussion_tag.discussion_id', $this->visibility->discussions()->select('discussions.id'))
            ->join('discussions', 'discussions.id', '=', 'discussion_tag.discussion_id')
            ->join('tags', 'tags.id', '=', 'discussion_tag.tag_id')
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('discussions.created_at', '>=', "$year-01-01 00:00:00")
            ->where('discussions.created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('tags.id', 'tags.name', 'tags.slug', 'tags.color')
            ->selectRaw('COUNT(DISTINCT '.$prefix.'discussions.id) as discussion_count')
            ->groupBy('tags.id', 'tags.name', 'tags.slug', 'tags.color')
            ->orderByDesc('discussion_count')
            ->first();

        if (! $result) {
            return ['id' => null, 'name' => null, 'slug' => null, 'discussion_count' => 0];
        }

        return [
            'id' => (int) $result->id,
            'name' => $result->name,
            'slug' => $result->slug,
            'color' => $result->color,
            'discussion_count' => (int) $result->discussion_count,
        ];
    }
}
