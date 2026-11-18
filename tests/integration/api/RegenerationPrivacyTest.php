<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Metric\MetricRegistry;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use HuseyinFiliz\Rewind\UserSnapshotGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RegenerationPrivacyTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-rewind');
        $this->setting('huseyinfiliz-rewind.enabled', true);
        $this->setting('huseyinfiliz-rewind.automatic_year', false);
        $this->setting('huseyinfiliz-rewind.active_year', 2025);
        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'reader', 'email' => 'reader@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 1],
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.viewForum'],
            ],
            'rw_snaps' => [
                ['id' => 1, 'user_id' => 2, 'year' => 2025, 'data' => json_encode(['marker' => 'original']), 'generated_at' => Carbon::now()->subMinutes(2)->toDateTimeString(), 'is_public' => true],
            ],
        ]);
    }

    public static function visibilityChanges(): array
    {
        return [
            'owner revokes publication' => [true, false],
            'owner publishes a private recap' => [false, true],
        ];
    }

    private function changeVisibility(bool $isPublic): void
    {
        $response = $this->send($this->request('PATCH', '/api/rw-snaps/1', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'rw-snaps', 'id' => '1', 'attributes' => ['isPublic' => $isPublic]]],
        ]));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame($isPublic, RewindSnapshot::findOrFail(1)->is_public);
        $this->assertReaderVisibility($isPublic);
    }

    private function assertReaderVisibility(bool $isPublic): void
    {
        $response = $this->send($this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 3]));
        $this->assertEquals($isPublic ? 200 : 404, $response->getStatusCode());
    }

    private function regenerate(bool $initial, bool $chosen, bool $beforeSave): void
    {
        $container = $this->app()->getContainer();
        $this->database()->table('rw_snaps')->where('id', 1)->update(['is_public' => $initial]);
        $container->make(SettingsRepositoryInterface::class)->set('huseyinfiliz-rewind.default_public', $initial);
        $this->assertReaderVisibility($initial);

        if ($beforeSave) {
            $pending = true;
            RewindSnapshot::saving(function (RewindSnapshot $snapshot) use (&$pending, $chosen): void {
                if ($pending && (int) $snapshot->id === 1 && ($snapshot->data['marker'] ?? null) === 'regenerated') {
                    $pending = false;
                    $this->changeVisibility($chosen);
                }
            });
        }

        $metrics = $this->createStub(MetricRegistry::class);
        $metrics->method('compute')->willReturnCallback(function () use ($beforeSave, $chosen): array {
            if (! $beforeSave) {
                $this->changeVisibility($chosen);
            }

            return ['marker' => 'regenerated'];
        });
        $container->instance(MetricRegistry::class, $metrics);

        $container->make(UserSnapshotGenerator::class)->generate(User::findOrFail(2), 2025, true);

        $snapshot = RewindSnapshot::findOrFail(1);
        $this->assertSame($chosen, $snapshot->is_public);
        $this->assertSame(['marker' => 'regenerated'], $snapshot->data);
        $this->assertSame(1, RewindSnapshot::count());
        $this->assertReaderVisibility($chosen);
    }

    #[Test]
    #[DataProvider('visibilityChanges')]
    public function regeneration_preserves_owner_changes_during_metric_calculation(bool $initial, bool $chosen): void
    {
        $this->regenerate($initial, $chosen, false);
    }

    #[Test]
    #[DataProvider('visibilityChanges')]
    public function regeneration_preserves_owner_changes_after_the_final_read(bool $initial, bool $chosen): void
    {
        $this->regenerate($initial, $chosen, true);
    }
}
