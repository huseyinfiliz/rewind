<?php

namespace HuseyinFiliz\Rewind\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use HuseyinFiliz\Rewind\RewindYear;
use HuseyinFiliz\Rewind\SnapshotVisibility;
use HuseyinFiliz\Rewind\UserSnapshotGenerator;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * @extends AbstractDatabaseResource<RewindSnapshot>
 */
class RewindSnapshotResource extends AbstractDatabaseResource
{
    public function __construct(
        protected UserSnapshotGenerator $generator,
        protected SettingsRepositoryInterface $settings,
        protected RewindYear $years,
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

        SnapshotVisibility::scope($query, $actor);

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
                    $year = (int) ($body['year'] ?? $this->years->active());
                    $groupId = isset($body['group']) ? (int) $body['group'] : null;

                    $query = User::where('is_email_confirmed', true)->orderBy('id');
                    $snapshotUsers = RewindSnapshot::where('year', $year)->select('user_id');
                    if (! empty($body['regenerate'])) {
                        $query->whereIn('id', $snapshotUsers);
                    } else {
                        $query->whereNotIn('id', $snapshotUsers);
                    }

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
                        ->toBase()->get();

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
                ->get(function (RewindSnapshot $snapshot, Context $context) {
                    if (! $snapshot->is_public) {
                        $actor = $context->getActor();
                        if ($actor->id !== $snapshot->user_id && ! $actor->can('huseyinfiliz-rewind.moderate')) {
                            return false;
                        }
                    }

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
                        if (isset($value['peak_hour'])) {
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
        $year = $this->years->active();
        $enabled = (bool) $this->settings->get('huseyinfiliz-rewind.enabled', false);

        if (! $actor->can('huseyinfiliz-rewind.moderate')) {
            if (! $enabled) {
                throw new \Flarum\User\Exception\PermissionDeniedException();
            }
            $actor->assertCan('huseyinfiliz-rewind.generate');
        }

        return $this->generator->generate($actor, $year, $actor->can('huseyinfiliz-rewind.moderate'), true);
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
        $activeYear = $this->years->active();
        $year = (int) ($body['year'] ?? $activeYear);

        if ($year < 2000 || $year > $activeYear) {
            throw new \Flarum\Foundation\ValidationException([
                'year' => 'Year must be between 2000 and '.$activeYear,
            ]);
        }

        return $this->generator->generate($user, $year);
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
            $userIds = User::whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))->select('id');
            $query->whereIn('user_id', $userIds);
        }

        return $query->delete();
    }
}
