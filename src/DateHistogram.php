<?php

namespace HuseyinFiliz\Rewind;

use Illuminate\Database\Eloquent\Builder;

class DateHistogram
{
    /**
     * @param Builder<\Flarum\Post\Post> $query
     * @return array<int, int>
     */
    public static function counts(Builder $query, string $unit): array
    {
        $column = $query->getQuery()->getGrammar()->wrap('posts.created_at');
        $expression = match ($query->getConnection()->getDriverName()) {
            'sqlite' => "CAST(strftime('".($unit === 'month' ? '%m' : '%H')."', {$column}) AS INTEGER)",
            'pgsql' => 'EXTRACT('.($unit === 'month' ? 'MONTH' : 'HOUR')." FROM {$column})",
            default => ($unit === 'month' ? 'MONTH' : 'HOUR')."({$column})",
        };

        $counts = $unit === 'month' ? array_fill(1, 12, 0) : array_fill(0, 24, 0);
        $rows = $query->selectRaw("{$expression} as bucket, COUNT(*) as aggregate")
            ->groupByRaw($expression)->toBase()->get();
        foreach ($rows as $row) {
            $counts[(int) $row->bucket] = (int) $row->aggregate;
        }

        return $counts;
    }
}
