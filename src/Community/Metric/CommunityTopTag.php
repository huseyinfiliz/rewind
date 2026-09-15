<?php

namespace HuseyinFiliz\Rewind\Community\Metric;

use HuseyinFiliz\Rewind\Community\CommunityMetric;
use Illuminate\Database\ConnectionInterface;

class CommunityTopTag implements CommunityMetric
{
    public function __construct(
        protected ConnectionInterface $db,
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
            ->join('discussions', 'discussions.id', '=', 'discussion_tag.discussion_id')
            ->join('tags', 'tags.id', '=', 'discussion_tag.tag_id')
            ->whereYear('discussions.created_at', $year)
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
