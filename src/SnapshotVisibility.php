<?php

namespace HuseyinFiliz\Rewind;

use Flarum\User\User;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use Illuminate\Database\Eloquent\Builder;

class SnapshotVisibility
{
    /** @param Builder<RewindSnapshot> $query */
    public static function scope(Builder $query, User $actor): void
    {
        if ($actor->can('huseyinfiliz-rewind.moderate')) {
            return;
        }

        $query->where(function (Builder $query) use ($actor) {
            $query->where('is_public', true);

            if (! $actor->isGuest()) {
                $query->orWhere('user_id', $actor->id);
            }
        });
    }
}
