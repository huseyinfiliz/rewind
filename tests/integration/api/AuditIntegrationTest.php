<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Audit\AuditLog;
use Flarum\Audit\AuditLogger;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Community\CommunityMetricRegistry;
use HuseyinFiliz\Rewind\CommunitySnapshotGenerator;
use HuseyinFiliz\Rewind\Integration\AuditIntegration;
use HuseyinFiliz\Rewind\Metric\MetricRegistry;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use HuseyinFiliz\Rewind\Template\RewindTemplateManager;
use HuseyinFiliz\Rewind\UserSnapshotGenerator;
use PHPUnit\Framework\Attributes\Test;

class AuditIntegrationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLogger::$testMode = true;
        $this->extension('flarum-audit', 'huseyinfiliz-rewind');
        $this->setting('huseyinfiliz-rewind.enabled', true);
        $this->setting('huseyinfiliz-rewind.active_year', 2025);
        $this->setting('huseyinfiliz-rewind.automatic_year', false);
        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'other', 'email' => 'other@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 1],
            ],
            'group_user' => [['user_id' => 3, 'group_id' => 4]],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.viewForum'],
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.generate'],
            ],
        ]);
    }

    private function snapshot(int $userId = 2, int $year = 2025): RewindSnapshot
    {
        $this->app();

        return RewindSnapshot::create([
            'user_id' => $userId,
            'year' => $year,
            'data' => ['content' => 'SNAPSHOT_CONTENT_MUST_NOT_BE_LOGGED'],
            'is_public' => true,
            'generated_at' => Carbon::now()->subMinutes(2),
        ]);
    }

    #[Test]
    public function visibility_logs_only_a_successful_change_with_the_owner_as_actor(): void
    {
        $snapshot = $this->snapshot();
        foreach ([3 => 403, 2 => 200] as $actor => $status) {
            $response = $this->send($this->request('PATCH', '/api/rw-snaps/'.$snapshot->id, [
                'authenticatedAs' => $actor,
                'json' => ['data' => ['type' => 'rw-snaps', 'id' => (string) $snapshot->id, 'attributes' => ['isPublic' => false]]],
            ]));
            $this->assertEquals($status, $response->getStatusCode());
        }
        $this->send($this->request('PATCH', '/api/rw-snaps/'.$snapshot->id, [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'rw-snaps', 'id' => (string) $snapshot->id, 'attributes' => ['isPublic' => false]]],
        ]));

        $logs = AuditLog::where('action', 'rewind.personal.visibility_changed')->get();
        $this->assertCount(1, $logs);
        $this->assertEquals(2, $logs->first()->actor_id);
        $this->assertSame([
            'snapshot_id' => (int) $snapshot->id, 'user_id' => 2, 'year' => 2025,
            'old_visibility' => 'public', 'visibility' => 'private',
        ], $logs->first()->payload);
        $this->assertSame(AuditIntegration::$actions, AuditLogger::$registeredActions['huseyinfiliz-rewind']);
    }

    #[Test]
    public function personal_generation_records_actor_and_target_without_metric_content(): void
    {
        $container = $this->app()->getContainer();
        $metrics = $this->createStub(MetricRegistry::class);
        $metrics->method('compute')->willReturn(['content' => 'SNAPSHOT_CONTENT_MUST_NOT_BE_LOGGED']);
        $container->instance(MetricRegistry::class, $metrics);

        foreach (['created', 'regenerated'] as $action) {
            $response = $this->send($this->request('POST', '/api/rw-snaps/generate-for-user', [
                'authenticatedAs' => 1, 'json' => ['userId' => 2, 'year' => 2025],
            ]));
            $this->assertEquals(200, $response->getStatusCode());
            $log = AuditLog::where('action', 'rewind.personal.'.$action)->sole();
            $this->assertEquals(1, $log->actor_id);
            $this->assertSame(['snapshot_id', 'year', 'user_id'], array_keys($log->payload));
            $this->assertSame(2, $log->payload['user_id']);
        }
        $this->assertSame(2, AuditLog::where('action', 'like', 'rewind.%')->count());
    }

    #[Test]
    public function direct_generation_has_no_fabricated_user_actor(): void
    {
        $container = $this->app()->getContainer();
        $metrics = $this->createStub(MetricRegistry::class);
        $metrics->method('compute')->willReturn([]);
        $container->instance(MetricRegistry::class, $metrics);
        AuditLogger::$actor = null;
        AuditLogger::$client = null;
        $container->make(UserSnapshotGenerator::class)->generate(User::findOrFail(2), 2025);

        $log = AuditLog::where('action', 'rewind.personal.created')->sole();
        $this->assertNull($log->actor_id);
        $this->assertSame('cli', $log->client);
        $this->assertSame(2, $log->payload['user_id']);
    }

    #[Test]
    public function community_generation_logs_publication_once_not_drafts_or_cached_reads(): void
    {
        $container = $this->app()->getContainer();
        $metrics = $this->createStub(CommunityMetricRegistry::class);
        $metrics->method('availableKeys')->willReturn(['total_posts', 'total_discussions']);
        $metrics->method('computeOne')->willReturn(['count' => 0]);
        $container->instance(CommunityMetricRegistry::class, $metrics);
        $generator = $container->make(CommunitySnapshotGenerator::class);

        $this->assertFalse($generator->step(2025)['ready']);
        $this->assertSame(0, AuditLog::where('action', 'like', 'rewind.community.%')->count());
        $this->assertTrue($generator->step(2025)['ready']);
        $generator->step(2025);
        $this->assertSame(1, AuditLog::where('action', 'rewind.community.created')->count());
        $generator->generate(2025, true);
        $this->assertSame(1, AuditLog::where('action', 'rewind.community.regenerated')->count());
        $this->assertSame(['snapshot_id', 'year'], array_keys(AuditLog::where('action', 'rewind.community.created')->sole()->payload));
    }

    #[Test]
    public function deletion_logs_success_and_batch_scope_without_duplicate_individual_logs(): void
    {
        $snapshot = $this->snapshot();
        $denied = $this->send($this->request('DELETE', '/api/rw-snaps/'.$snapshot->id, ['authenticatedAs' => 2]));
        $this->assertEquals(403, $denied->getStatusCode());
        $this->assertSame(0, AuditLog::where('action', 'rewind.personal.deleted')->count());
        $deleted = $this->send($this->request('DELETE', '/api/rw-snaps/'.$snapshot->id, ['authenticatedAs' => 1]));
        $this->assertEquals(204, $deleted->getStatusCode());
        $this->assertEquals(1, AuditLog::where('action', 'rewind.personal.deleted')->sole()->actor_id);

        $this->snapshot();
        $this->snapshot(3);
        $this->snapshot(3, 2024);
        foreach ([1, 0] as $count) {
            $response = $this->send($this->request('POST', '/api/rw-snaps/batch-delete', [
                'authenticatedAs' => 1, 'json' => ['year' => 2025, 'group' => 4],
            ]));
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertSame($count, json_decode((string) $response->getBody(), true)['deleted']);
        }
        $log = AuditLog::where('action', 'rewind.personal.batch_deleted')->sole();
        $this->assertEquals(1, $log->actor_id);
        $this->assertSame(['year' => 2025, 'group_id' => 4, 'deleted_count' => 1], $log->payload);
        $this->assertSame(1, AuditLog::where('action', 'rewind.personal.deleted')->count());
        $this->assertSame(2, RewindSnapshot::count());
    }

    #[Test]
    public function templates_log_success_without_content_and_skip_rejected_or_unchanged_updates(): void
    {
        $manager = $this->app()->getContainer()->make(RewindTemplateManager::class);
        $path = $manager->getStorageViewsPath().'/user_2099.blade.php';
        $this->assertFileDoesNotExist($path);

        try {
            $denied = $this->send($this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 2, 'json' => ['type' => 'user', 'year' => 2099],
            ]));
            $this->assertEquals(403, $denied->getStatusCode());
            $created = $this->send($this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1, 'json' => ['type' => 'user', 'year' => 2099],
            ]));
            $this->assertEquals(201, $created->getStatusCode());
            foreach (['<p>TEMPLATE_CONTENT_MUST_NOT_BE_LOGGED</p>', '<p>TEMPLATE_CONTENT_MUST_NOT_BE_LOGGED</p>', '<?php echo "rejected";'] as $content) {
                $response = $this->send($this->request('PUT', '/api/rewind-templates/user_2099', [
                    'authenticatedAs' => 1, 'json' => ['content' => $content],
                ]));
                $this->assertEquals(str_starts_with($content, '<?php') ? 400 : 200, $response->getStatusCode());
            }
            $deleted = $this->send($this->request('DELETE', '/api/rewind-templates/user_2099', ['authenticatedAs' => 1]));
            $this->assertEquals(200, $deleted->getStatusCode());
            foreach (['created', 'updated', 'deleted'] as $action) {
                $log = AuditLog::where('action', 'rewind.template.'.$action)->sole();
                $this->assertEquals(1, $log->actor_id);
                $this->assertSame(['template_id' => 'user_2099'], $log->payload);
            }
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    #[Test]
    public function rewind_still_generates_updates_and_deletes_without_audit_enabled(): void
    {
        $this->extensions = ['huseyinfiliz-rewind'];
        $generated = $this->send($this->request('POST', '/api/rw-snaps/generate', ['authenticatedAs' => 2]));
        $this->assertEquals(201, $generated->getStatusCode());
        $id = json_decode((string) $generated->getBody(), true)['data']['id'];
        $updated = $this->send($this->request('PATCH', '/api/rw-snaps/'.$id, [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'rw-snaps', 'id' => $id, 'attributes' => ['isPublic' => true]]],
        ]));
        $this->assertEquals(200, $updated->getStatusCode());
        $deleted = $this->send($this->request('DELETE', '/api/rw-snaps/'.$id, ['authenticatedAs' => 1]));
        $this->assertEquals(204, $deleted->getStatusCode());
        $this->assertArrayNotHasKey('huseyinfiliz-rewind', AuditLogger::$registeredActions);
    }
}
