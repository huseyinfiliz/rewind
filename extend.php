<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind;

use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use HuseyinFiliz\Rewind\Api\Resource\CommunitySnapshotResource;
use HuseyinFiliz\Rewind\Api\Resource\RewindSnapshotResource;
use HuseyinFiliz\Rewind\Http\Controller\ShowCommunityRewindBladeController;
use HuseyinFiliz\Rewind\Http\Controller\ShowUserRewindBladeController;
use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/rewind', 'huseyinfiliz-rewind.forum')
        ->route('/u/{username}/rewind', 'huseyinfiliz-rewind.profile'),

    (new Extend\Routes('forum'))
        ->get('/rewind/view/{id:[0-9]+}/{year:[0-9]+}', 'huseyinfiliz-rewind.blade.user', ShowUserRewindBladeController::class)
        ->get('/rewind/view/{year:[0-9]+}', 'huseyinfiliz-rewind.blade.community', ShowCommunityRewindBladeController::class)
        ->get('/rewind/view', 'huseyinfiliz-rewind.blade.community-default', ShowCommunityRewindBladeController::class),

    (new Extend\View())
        ->namespace('rewind', __DIR__.'/resources/views'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ServiceProvider())
        ->register(RewindServiceProvider::class),

    new Extend\ApiResource(RewindSnapshotResource::class),

    new Extend\ApiResource(CommunitySnapshotResource::class),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewRewind')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('huseyinfiliz-rewind.viewForum')),
            Schema\Boolean::make('canGenerateRewind')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('huseyinfiliz-rewind.generate')),
            Schema\Boolean::make('canModerateRewind')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('huseyinfiliz-rewind.moderate')),
        ]),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(RewindSnapshot::class, Search\RewindSnapshotSearcher::class)
        ->addFilter(Search\RewindSnapshotSearcher::class, Search\Filter\UserFilter::class)
        ->addFilter(Search\RewindSnapshotSearcher::class, Search\Filter\YearFilter::class)
        ->addSearcher(CommunitySnapshot::class, Search\CommunitySnapshotSearcher::class)
        ->addFilter(Search\CommunitySnapshotSearcher::class, Search\Filter\YearFilter::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-likes', fn () => [
            (new Extend\ServiceProvider())
                ->register(Metric\Optional\LikesMetricsProvider::class),
        ])
        ->whenExtensionEnabled('flarum-mentions', fn () => [
            (new Extend\ServiceProvider())
                ->register(Metric\Optional\MentionsMetricsProvider::class),
        ])
        ->whenExtensionEnabled('fof-badges', fn () => [
            (new Extend\ServiceProvider())
                ->register(Metric\Optional\BadgesMetricsProvider::class),
        ])
        ->whenExtensionEnabled('flarum-best-answer', fn () => [
            (new Extend\ServiceProvider())
                ->register(Metric\Optional\BestAnswersMetricsProvider::class),
        ]),

    (new Extend\Settings())
        ->serializeToForum('rewindEnabled', 'huseyinfiliz-rewind.enabled', 'boolval')
        ->serializeToForum('rewindYearRenderModes', 'huseyinfiliz-rewind.year_render_modes')
        ->serializeToForum('rewindActiveYear', 'huseyinfiliz-rewind.active_year', 'intval')
        ->serializeToForum('rewindShowMenu', 'huseyinfiliz-rewind.show_menu_link', 'boolval')
        ->serializeToForum('rewindHiddenUserSlides', 'huseyinfiliz-rewind.hidden_user_slides')
        ->serializeToForum('rewindHiddenCommunitySlides', 'huseyinfiliz-rewind.hidden_community_slides')
        ->default('huseyinfiliz-rewind.enabled', false)
        ->default('huseyinfiliz-rewind.year_render_modes', '{}')
        ->default('huseyinfiliz-rewind.active_year', 2025)
        ->default('huseyinfiliz-rewind.show_menu_link', true)
        ->default('huseyinfiliz-rewind.hidden_user_slides', '[]')
        ->default('huseyinfiliz-rewind.hidden_community_slides', '[]')
        ->default('huseyinfiliz-rewind.community_comparison_enabled', false),
];
