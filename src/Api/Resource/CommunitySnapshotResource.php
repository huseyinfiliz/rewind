<?php

namespace HuseyinFiliz\Rewind\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Rewind\Community\CommunityMetricRegistry;
use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * @extends AbstractDatabaseResource<CommunitySnapshot>
 */
class CommunitySnapshotResource extends AbstractDatabaseResource
{
    public function __construct(
        protected CommunityMetricRegistry $metricRegistry,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function type(): string
    {
        return 'rw-community';
    }

    public function model(): string
    {
        return CommunitySnapshot::class;
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
            Endpoint\Endpoint::make('generate-steps')
                ->route('POST', '/generate-steps')
                ->authenticated()
                ->admin()
                ->action(function (Context $context) {
                    return null;
                })
                ->response(function () {
                    return new JsonResponse([
                        'steps' => $this->metricRegistry->availableKeys(),
                    ]);
                }),
            Endpoint\Show::make(),
            Endpoint\Index::make()
                ->paginate(10, 20)
                ->defaultSort('-year'),
            Endpoint\Endpoint::make('generate')
                ->route('POST', '/generate')
                ->authenticated()
                ->admin()
                ->action(function (Context $context) {
                    return $this->generate($context);
                })
                ->response(function (Context $context, CommunitySnapshot $snapshot) {
                    return new JsonResponse(
                        [
                            'data' => [
                                'type' => 'rw-community',
                                'id' => (string) $snapshot->id,
                                'attributes' => [
                                    'year' => $snapshot->year,
                                    'data' => $snapshot->data,
                                    'generatedAt' => $snapshot->generated_at->toIso8601String(),
                                ],
                            ],
                        ],
                        201
                    );
                }),
            Endpoint\Endpoint::make('generate-step')
                ->route('POST', '/generate-step')
                ->authenticated()
                ->admin()
                ->action(function (Context $context) {
                    return $this->generateStep($context);
                })
                ->response(function (Context $context, CommunitySnapshot $snapshot) {
                    return new JsonResponse([
                        'data' => [
                            'type' => 'rw-community',
                            'id' => (string) $snapshot->id,
                            'attributes' => [
                                'year' => $snapshot->year,
                                'data' => $snapshot->data,
                                'generatedAt' => $snapshot->generated_at->toIso8601String(),
                            ],
                        ],
                    ]);
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('year'),
            Schema\Arr::make('data'),
            Schema\DateTime::make('generatedAt'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('year'),
        ];
    }

    protected function generate(Context $context): CommunitySnapshot
    {
        $year = (int) $this->settings->get('huseyinfiliz-rewind.active_year', date('Y'));

        // Rate limit: 1 regenerate per minute per year
        $existing = CommunitySnapshot::where('year', $year)->first();
        if ($existing && $existing->generated_at && $existing->generated_at->diffInSeconds(Carbon::now()) < 60) {
            throw new \Flarum\Foundation\ValidationException([
                'rate_limit' => 'Please wait at least 1 minute before regenerating.',
            ]);
        }

        $data = $this->metricRegistry->compute($year);

        return CommunitySnapshot::updateOrCreate(
            ['year' => $year],
            ['data' => $data, 'generated_at' => Carbon::now()]
        );
    }

    protected function generateStep(Context $context): CommunitySnapshot
    {
        $request = $context->request;
        $body = $request->getParsedBody();

        if (empty($body)) {
            $raw = (string) $request->getBody();
            $body = json_decode($raw, true) ?: [];
        }

        $step = $body['step'] ?? null;
        $activeYear = (int) $this->settings->get('huseyinfiliz-rewind.active_year', date('Y'));
        $year = (int) ($body['year'] ?? $activeYear);

        if ($year < 2000 || $year > $activeYear) {
            throw new \Flarum\Foundation\ValidationException([
                'year' => 'Year must be between 2000 and '.$activeYear,
            ]);
        }

        if (! $step || ! is_string($step)) {
            throw new \Flarum\Foundation\ValidationException([
                'step' => 'A valid metric step key is required.',
            ]);
        }

        $snapshot = CommunitySnapshot::firstOrCreate(
            ['year' => $year],
            ['data' => [], 'generated_at' => Carbon::now()]
        );

        $result = $this->metricRegistry->computeOne($step, $year);

        if ($result !== null) {
            $data = $snapshot->data ?? [];
            $data[$step] = $result;
            $snapshot->data = $data;
            $snapshot->generated_at = Carbon::now();
            $snapshot->save();
        }

        return $snapshot;
    }
}
