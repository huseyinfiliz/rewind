<?php

namespace HuseyinFiliz\Rewind\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Metric\MetricRegistry;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * @extends AbstractDatabaseResource<RewindSnapshot>
 */
class RewindSnapshotResource extends AbstractDatabaseResource
{
    public function __construct(
        protected MetricRegistry $metricRegistry,
        protected SettingsRepositoryInterface $settings,
        protected ?\Illuminate\Contracts\Cache\Repository $cache = null,
    ) {
    }

    public function type(): string
    {
        return 'rw-snaps';
    }

    public function model(): string
    {
        return RewindSnapshot::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        /** @var Context $context */
        $actor = $context->getActor();

        if (! $actor->can('huseyinfiliz-rewind.moderate')) {
            $enabled = (bool) $this->settings->get('huseyinfiliz-rewind.enabled', false);

            if (! $enabled || ! $actor->can('huseyinfiliz-rewind.viewForum')) {
                $query->whereRaw('0 = 1');
            }
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Endpoint::make('missing-users')
                ->route('POST', '/missing-users')
                ->authenticated()
                ->admin()
                ->action(fn (Context $context) => null)
                ->response(function (Context $context) {
                    $body = $context->request->getParsedBody();
                    if (empty($body)) {
                        $raw = (string) $context->request->getBody();
                        $body = json_decode($raw, true) ?: [];
                    }
                    $year = (int) ($body['year'] ?? $this->settings->get('huseyinfiliz-rewind.active_year', date('Y')));
                    $groupId = isset($body['group']) ? (int) $body['group'] : null;

                    $query = User::where('is_email_confirmed', true)
                        ->whereNotIn('id', RewindSnapshot::where('year', $year)->select('user_id'))
                        ->orderBy('id');

                    if ($groupId) {
                        $query->whereHas('groups', fn ($q) => $q->where('groups.id', $groupId));
                    }

                    $users = $query->select(['id', 'username'])->get();

                    return new JsonResponse([
                        'users' => $users->map(fn ($u) => ['id' => $u->id, 'username' => $u->username])->values()->all(),
                    ]);
                }),
            Endpoint\Endpoint::make('generate-for-user')
                ->route('POST', '/generate-for-user')
                ->authenticated()
                ->admin()
                ->action(function (Context $context) {
                    return $this->generateForUser($context);
                })
                ->response(function (Context $context, RewindSnapshot $snapshot) {
                    return new JsonResponse([
                        'data' => [
                            'type' => 'rw-snaps',
                            'id' => (string) $snapshot->id,
                            'attributes' => [
                                'year' => $snapshot->year,
                                'userId' => $snapshot->user_id,
                            ],
                        ],
                    ]);
                }),
            Endpoint\Endpoint::make('groups')
                ->route('POST', '/groups')
                ->authenticated()
                ->admin()
                ->action(fn (Context $context) => null)
                ->response(function () {
                    $groups = Group::withCount('users')->get();

                    return new JsonResponse([
                        'groups' => $groups->map(fn ($g) => [
                            'id' => $g->id,
                            'nameSingular' => $g->name_singular,
                            'namePlural' => $g->name_plural,
                            'count' => (int) ($g->users_count ?? 0),
                        ])->all(),
                    ]);
                }),
            Endpoint\Endpoint::make('year-stats')
                ->route('POST', '/year-stats')
                ->authenticated()
                ->admin()
                ->action(fn (Context $context) => null)
                ->response(function () {
                    $stats = RewindSnapshot::selectRaw('year, COUNT(*) as count')
                        ->groupBy('year')
                        ->orderByDesc('year')
                        ->get();

                    return new JsonResponse([
                        'years' => $stats->map(fn ($r) => ['year' => $r->year, 'count' => $r->count])->all(),
                    ]);
                }),
            Endpoint\Endpoint::make('batch-delete')
                ->route('POST', '/batch-delete')
                ->authenticated()
                ->admin()
                ->action(function (Context $context) {
                    return $this->batchDelete($context);
                })
                ->response(function (Context $context, $count) {
                    return new JsonResponse(['deleted' => $count]);
                }),
            Endpoint\Show::make(),
            Endpoint\Index::make()
                ->paginate(20, 50)
                ->defaultSort('-generatedAt'),
            Endpoint\Update::make()
                ->authenticated()
                ->visible(function (RewindSnapshot $snapshot, Context $context) {
                    $actor = $context->getActor();

                    return $actor->id === $snapshot->user_id || $actor->can('huseyinfiliz-rewind.moderate');
                }),
            Endpoint\Delete::make()
                ->authenticated()
                ->visible(function (RewindSnapshot $snapshot, Context $context) {
                    return $context->getActor()->can('huseyinfiliz-rewind.moderate');
                }),
            Endpoint\Endpoint::make('generate')
                ->route('POST', '/generate')
                ->authenticated()
                ->action(function (Context $context) {
                    return $this->generate($context);
                })
                ->response(function (Context $context, RewindSnapshot $snapshot) {
                    return new JsonResponse(
                        [
                            'data' => [
                                'type' => 'rw-snaps',
                                'id' => (string) $snapshot->id,
                                'attributes' => [
                                    'year' => $snapshot->year,
                                    'data' => $snapshot->data,
                                    'generatedAt' => $snapshot->generated_at->toIso8601String(),
                                    'isPublic' => $snapshot->is_public,
                                ],
                            ],
                        ],
                        201
                    );
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('year'),
            Schema\Arr::make('data')
                ->visible(function (RewindSnapshot $snapshot, Context $context) {
                    if ($snapshot->is_public) {
                        return true;
                    }
                    $actor = $context->getActor();

                    return $actor->id === $snapshot->user_id || $actor->can('huseyinfiliz-rewind.moderate');
                }),
            Schema\DateTime::make('generatedAt'),
            Schema\Boolean::make('isPublic')
                ->writable(function (RewindSnapshot $snapshot, Context $context) {
                    return $context->getActor()->id === $snapshot->user_id;
                }),
            Schema\Boolean::make('canEdit')
                ->get(function (RewindSnapshot $snapshot, Context $context) {
                    $actor = $context->getActor();

                    return $actor->id === $snapshot->user_id || $actor->can('huseyinfiliz-rewind.moderate');
                }),
            Schema\Boolean::make('canModerate')
                ->get(function (RewindSnapshot $snapshot, Context $context) {
                    return $context->getActor()->can('huseyinfiliz-rewind.moderate');
                }),
            Schema\Boolean::make('isEmpty')
                ->get(function (RewindSnapshot $snapshot) {
                    $data = $snapshot->data;
                    if (! is_array($data)) {
                        return true;
                    }

                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, '_')) {
                            continue;
                        }
                        if (! is_array($value)) {
                            continue;
                        }

                        // Check for any meaningful content
                        if (isset($value['count']) && $value['count'] > 0) {
                            return false;
                        }
                        if (isset($value['total']) && $value['total'] > 0) {
                            return false;
                        }
                        if (isset($value['user_id']) && $value['user_id']) {
                            return false;
                        }
                        if (isset($value['post_id']) && $value['post_id']) {
                            return false;
                        }
                        if (isset($value['words']) && ! empty($value['words'])) {
                            return false;
                        }
                        if (isset($value['emojis']) && ! empty($value['emojis'])) {
                            return false;
                        }
                        if (isset($value['peak_hour']) && $value['peak_hour'] !== null) {
                            return false;
                        }
                        if (isset($value['peak_month']) && $value['peak_month'] > 0) {
                            return false;
                        }
                    }

                    return true;
                }),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('generatedAt')
                ->column('generated_at'),
            SortColumn::make('year'),
        ];
    }

    protected function generate(Context $context): RewindSnapshot
    {
        $actor = $context->getActor();
        $year = (int) $this->settings->get('huseyinfiliz-rewind.active_year', date('Y'));
        $enabled = (bool) $this->settings->get('huseyinfiliz-rewind.enabled', false);

        if (! $actor->can('huseyinfiliz-rewind.moderate')) {
            if (! $enabled) {
                throw new \Flarum\User\Exception\PermissionDeniedException();
            }
            $actor->assertCan('huseyinfiliz-rewind.generate');
        }

        // Rate limit: 1 generate per minute per user (any year)
        $lastSnapshot = RewindSnapshot::where('user_id', $actor->id)
            ->orderByDesc('generated_at')
            ->first();

        if ($lastSnapshot && $lastSnapshot->generated_at && $lastSnapshot->generated_at->diffInSeconds(Carbon::now()) < 60) {
            throw new \Flarum\Foundation\ValidationException([
                'rate_limit' => 'Please wait at least 1 minute before generating.',
            ]);
        }

        $existing = RewindSnapshot::where('user_id', $actor->id)->where('year', $year)->first();

        if ($existing && ! $actor->can('huseyinfiliz-rewind.moderate')) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }

        if ($this->cache && ! $this->cache->add("rewind_generating_{$actor->id}", 1, 30)) {
            throw new \Flarum\Foundation\ValidationException([
                'rate_limit' => 'Snapshot generation is already in progress.',
            ]);
        }

        try {
            $data = $this->metricRegistry->compute($actor, $year);

            // Inject community averages if enabled
            if ($this->settings->get('huseyinfiliz-rewind.community_comparison_enabled')) {
                $communitySnapshot = \HuseyinFiliz\Rewind\Model\CommunitySnapshot::where('year', $year)->first();
                if ($communitySnapshot && $communitySnapshot->data) {
                    $cd = $communitySnapshot->data;
                    $memberCount = max(1, $cd['new_users']['count'] ?? 1);
                    $totalPosts = $cd['total_posts']['count'] ?? 0;
                    $totalDiscussions = $cd['total_discussions']['count'] ?? 0;
                    $totalWords = $cd['total_words']['total_words'] ?? 0;

                    $data['_community_avg'] = [
                        'posts' => $memberCount > 0 ? round($totalPosts / $memberCount, 1) : 0,
                        'discussions' => $memberCount > 0 ? round($totalDiscussions / $memberCount, 1) : 0,
                        'words' => $memberCount > 0 ? round($totalWords / $memberCount) : 0,
                    ];
                }
            }

            $snapshot = RewindSnapshot::updateOrCreate(
                ['user_id' => $actor->id, 'year' => $year],
                ['data' => $data, 'generated_at' => Carbon::now(), 'is_public' => $existing ? $existing->is_public : true]
            );

            return $snapshot;
        } finally {
            $this->cache?->forget("rewind_generating_{$actor->id}");
        }
    }

    protected function generateForUser(Context $context): RewindSnapshot
    {
        $request = $context->request;
        $body = $request->getParsedBody();

        if (empty($body)) {
            $raw = (string) $request->getBody();
            $body = json_decode($raw, true) ?: [];
        }

        $userId = (int) ($body['userId'] ?? 0);

        if (! $userId) {
            throw new \Flarum\Foundation\ValidationException([
                'userId' => 'A valid user ID is required.',
            ]);
        }

        $user = User::findOrFail($userId);
        $activeYear = (int) $this->settings->get('huseyinfiliz-rewind.active_year', date('Y'));
        $year = (int) ($body['year'] ?? $activeYear);

        if ($year < 2000 || $year > $activeYear) {
            throw new \Flarum\Foundation\ValidationException([
                'year' => 'Year must be between 2000 and '.$activeYear,
            ]);
        }

        $data = $this->metricRegistry->compute($user, $year);

        // Inject community averages if enabled
        if ($this->settings->get('huseyinfiliz-rewind.community_comparison_enabled')) {
            $communitySnapshot = \HuseyinFiliz\Rewind\Model\CommunitySnapshot::where('year', $year)->first();
            if ($communitySnapshot && $communitySnapshot->data) {
                $cd = $communitySnapshot->data;
                $memberCount = max(1, $cd['new_users']['count'] ?? 1);
                $totalPosts = $cd['total_posts']['count'] ?? 0;
                $totalDiscussions = $cd['total_discussions']['count'] ?? 0;
                $totalWords = $cd['total_words']['total_words'] ?? 0;

                $data['_community_avg'] = [
                    'posts' => $memberCount > 0 ? round($totalPosts / $memberCount, 1) : 0,
                    'discussions' => $memberCount > 0 ? round($totalDiscussions / $memberCount, 1) : 0,
                    'words' => $memberCount > 0 ? round($totalWords / $memberCount) : 0,
                ];
            }
        }

        return RewindSnapshot::updateOrCreate(
            ['user_id' => $userId, 'year' => $year],
            ['data' => $data, 'generated_at' => Carbon::now(), 'is_public' => true]
        );
    }

    protected function batchDelete(Context $context): int
    {
        $request = $context->request;
        $body = $request->getParsedBody();

        if (empty($body)) {
            $raw = (string) $request->getBody();
            $body = json_decode($raw, true) ?: [];
        }

        $year = (int) ($body['year'] ?? 0);

        if ($year < 2000 || $year > 2100) {
            throw new \Flarum\Foundation\ValidationException([
                'year' => 'Year must be between 2000 and 2100.',
            ]);
        }

        $query = RewindSnapshot::where('year', $year);

        $groupId = isset($body['group']) ? (int) $body['group'] : null;
        if ($groupId) {
            $userIds = User::whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))->pluck('id');
            $query->whereIn('user_id', $userIds);
        }

        return $query->delete();
    }
}
