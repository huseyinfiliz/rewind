<?php

namespace HuseyinFiliz\Rewind\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\Search\Filter\FilterManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use HuseyinFiliz\Rewind\SnapshotVisibility;
use Illuminate\Database\Eloquent\Builder;

class RewindSnapshotSearcher extends AbstractSearcher
{
    public function __construct(
        FilterManager $filters,
        array $mutators,
        protected SettingsRepositoryInterface $settings,
    ) {
        parent::__construct($filters, $mutators);
    }

    public function getQuery(User $actor): Builder
    {
        $query = RewindSnapshot::query()->select('rw_snaps.*');

        SnapshotVisibility::scope($query, $actor);

        $enabled = (bool) $this->settings->get('huseyinfiliz-rewind.enabled', false);

        if (! $actor->hasPermission('huseyinfiliz-rewind.moderate')) {
            if (! $enabled || ! $actor->hasPermission('huseyinfiliz-rewind.viewForum')) {
                $query->whereRaw('0 = 1');

                return $query;
            }
        }

        return $query;
    }
}
