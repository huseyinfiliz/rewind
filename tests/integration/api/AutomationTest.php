<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use HuseyinFiliz\Rewind\CommunitySnapshotGenerator;
use HuseyinFiliz\Rewind\Console\GenerateCommand;
use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

class AutomationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-01 00:00:00 UTC');
        $this->extension('huseyinfiliz-rewind');
        $this->setting('huseyinfiliz-rewind.enabled', true);
        $this->setting('huseyinfiliz-rewind.active_year', 2025);
        $this->setting('huseyinfiliz-rewind.automatic_community', true);
        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'member', 'email' => 'member@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'unconfirmed', 'email' => 'unconfirmed@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 0],
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.viewForum'],
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.generate'],
                ['group_id' => 2, 'permission' => 'viewForum'],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'First', 'slug' => 'first', 'user_id' => 2, 'created_at' => '2025-01-01 00:00:00', 'comment_count' => 1],
                ['id' => 2, 'title' => 'Second', 'slug' => 'second', 'user_id' => 3, 'created_at' => '2025-12-31 23:59:59', 'comment_count' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-01-01 00:00:00', 'content' => '<t>hello world</t>'],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 3, 'type' => 'comment', 'created_at' => '2025-12-31 23:59:59', 'content' => '<t>other post</t>'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ensure(int $actor = 2, int $year = 2025): \Psr\Http\Message\ResponseInterface
    {
        return $this->send($this->request('POST', '/api/rw-community/ensure', [
            'authenticatedAs' => $actor, 'json' => ['year' => $year],
        ]));
    }

    #[Test]
    public function first_members_cooperatively_generate_one_complete_snapshot_and_reuse_it()
    {
        $response = $this->ensure();
        $this->assertEquals(202, $response->getStatusCode());
        $this->assertSame(0, CommunitySnapshot::count());
        $list = $this->send($this->request('GET', '/api/rw-community', ['authenticatedAs' => 2]));
        $this->assertSame([], json_decode((string) $list->getBody(), true)['data']);

        $ready = false;
        for ($i = 0; $i < 30 && ! $ready; $i++) {
            $response = $this->ensure($i % 2 === 0 ? 3 : 2);
            $this->assertContains($response->getStatusCode(), [200, 202]);
            $ready = json_decode((string) $response->getBody(), true)['ready'];
        }
        $this->assertTrue($ready);
        $this->assertSame(1, CommunitySnapshot::count());
        $snapshot = CommunitySnapshot::firstOrFail();
        $this->assertEquals(2, $snapshot->data['total_posts']['count']);
        $this->assertEquals(4, $snapshot->data['total_words']['total_words']);
        $this->assertEquals(2, $snapshot->data['active_members']['count']);
        $generated = $snapshot->generated_at->toIso8601String();
        $this->assertEquals(200, $this->ensure()->getStatusCode());
        $this->assertSame($generated, $snapshot->fresh()->generated_at->toIso8601String());
    }

    #[Test]
    public function automation_rejects_guests_and_unconfirmed_members()
    {
        $response = $this->send($this->request('POST', '/api/rw-community/ensure'));
        $this->assertNotContains($response->getStatusCode(), [200, 202]);
        $this->assertEquals(403, $this->ensure(4)->getStatusCode());
        $this->assertSame(0, CommunitySnapshot::count());
    }

    #[Test]
    public function automation_requires_view_permission()
    {
        $this->database()->table('group_permission')->where('permission', 'huseyinfiliz-rewind.viewForum')->delete();
        $this->assertEquals(403, $this->ensure()->getStatusCode());
        $this->assertSame(0, CommunitySnapshot::count());
    }

    #[Test]
    public function automation_can_be_disabled()
    {
        $this->setting('huseyinfiliz-rewind.automatic_community', false);
        $this->assertEquals(403, $this->ensure()->getStatusCode());
        $this->assertSame(0, CommunitySnapshot::count());
    }

    #[Test]
    public function disabled_rewind_cannot_be_automatically_generated()
    {
        $this->setting('huseyinfiliz-rewind.enabled', false);
        $this->assertEquals(403, $this->ensure()->getStatusCode());
    }

    #[Test]
    public function members_cannot_trigger_historical_or_unfinished_years()
    {
        $this->assertEquals(422, $this->ensure(year: 2024)->getStatusCode());
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('huseyinfiliz-rewind.active_year', 2099);
        $this->assertEquals(422, $this->ensure(year: 2099)->getStatusCode());
        $this->assertSame(0, CommunitySnapshot::count());
    }

    #[Test]
    public function word_analysis_is_bounded_and_drafts_are_not_published()
    {
        $rows = [];
        for ($id = 100; $id <= 1100; $id++) {
            $rows[] = ['id' => $id, 'discussion_id' => 1, 'number' => $id, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-06-01 12:00:00', 'content' => '<t>two words</t>'];
        }
        $this->database()->table('posts')->insert($rows);
        $generator = $this->app()->getContainer()->make(CommunitySnapshotGenerator::class);
        $first = $generator->step(2025, 'total_words');
        $this->assertFalse($first['stepComplete']);
        $this->assertSame(0, CommunitySnapshot::count());
        $second = $generator->step(2025, 'total_words');
        $this->assertTrue($second['stepComplete']);
        $this->assertFalse($second['ready']);
        $snapshot = $generator->generate(2025);
        $this->assertEquals(1003, $snapshot->data['total_words']['total_posts']);
        $this->assertEquals(2006, $snapshot->data['total_words']['total_words']);
    }

    #[Test]
    public function regeneration_keeps_the_published_snapshot_until_all_steps_finish()
    {
        $generator = $this->app()->getContainer()->make(CommunitySnapshotGenerator::class);
        $snapshot = $generator->generate(2025);
        $original = $snapshot->data;
        $generator->begin(2025);
        $progress = $generator->step(2025, 'total_posts', true);
        $this->assertFalse($progress['ready']);
        $this->assertSame($original, $snapshot->fresh()->data);
    }

    #[Test]
    public function averages_use_active_members_instead_of_new_registrations()
    {
        $this->setting('huseyinfiliz-rewind.community_comparison_enabled', true);
        $this->app()->getContainer()->make(CommunitySnapshotGenerator::class)->generate(2025);
        $response = $this->send($this->request('POST', '/api/rw-snaps/generate', ['authenticatedAs' => 2]));
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true)['data']['attributes']['data'];
        $this->assertEquals(1, $data['_community_avg']['posts']);
        $this->assertEquals(1, $data['_community_avg']['discussions']);
        $this->assertEquals(2, $data['_community_avg']['words']);
    }

    #[Test]
    public function scheduler_honors_automation_setting()
    {
        $this->setting('huseyinfiliz-rewind.automatic_community', false);
        $command = new CommandTester($this->app()->getContainer()->make(GenerateCommand::class));
        $this->assertSame(0, $command->execute(['--automatic' => true]));
        $this->assertSame(0, CommunitySnapshot::count());
    }

    #[Test]
    public function scheduler_prepares_the_active_completed_year()
    {
        $command = new CommandTester($this->app()->getContainer()->make(GenerateCommand::class));
        $this->assertSame(0, $command->execute(['--automatic' => true]));
        $this->assertSame(1, CommunitySnapshot::where('year', 2025)->count());
        $this->assertSame(0, $command->execute(['--automatic' => true]));
        $this->assertSame(1, CommunitySnapshot::count());
    }

    #[Test]
    public function automatic_year_is_enabled_by_default_and_used_for_personal_generation()
    {
        Carbon::setTestNow('2027-01-01 00:00:00 UTC');
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2026, $body['data']['attributes']['rewindActiveYear']);
        $response = $this->send($this->request('POST', '/api/rw-snaps/generate', ['authenticatedAs' => 2]));
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(2026, json_decode((string) $response->getBody(), true)['data']['attributes']['year']);
    }

    #[Test]
    public function explicitly_disabling_automatic_year_restores_the_saved_manual_year()
    {
        Carbon::setTestNow('2027-01-01 00:00:00 UTC');
        $this->setting('huseyinfiliz-rewind.automatic_year', false);
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2025, $body['data']['attributes']['rewindActiveYear']);
    }

    #[Test]
    public function an_unfinished_manually_selected_year_is_not_automatically_generated()
    {
        $this->setting('huseyinfiliz-rewind.automatic_year', false);
        $this->setting('huseyinfiliz-rewind.active_year', 2026);
        $this->assertEquals(422, $this->ensure(year: 2026)->getStatusCode());
        $this->assertSame(0, CommunitySnapshot::count());
    }

    #[Test]
    public function year_ranges_include_the_final_fractional_second_and_exclude_neighboring_years()
    {
        $this->database()->table('posts')->insert([
            ['id' => 10, 'discussion_id' => 1, 'number' => 10, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2024-12-31 23:59:59', 'content' => '<t>excluded words</t>'],
            ['id' => 11, 'discussion_id' => 1, 'number' => 11, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2026-01-01 00:00:00', 'content' => '<t>excluded words</t>'],
            ['id' => 12, 'discussion_id' => 1, 'number' => 12, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-12-31 23:59:59.999999', 'content' => '<t>included words</t>'],
        ]);
        $data = $this->app()->getContainer()->make(CommunitySnapshotGenerator::class)->generate(2025)->data;
        $this->assertEquals(3, $data['total_posts']['count']);
        $this->assertEquals(6, $data['total_words']['total_words']);
        $this->assertEquals(12, $data['busiest_month']['peak_month']);
        $this->assertEquals(2, $data['busiest_month']['peak_count']);
        $this->assertEquals(23, $data['peak_hour']['peak_hour']);
        $this->assertEquals(2, $data['peak_hour']['peak_count']);
    }

    #[Test]
    public function cli_refreshes_existing_personal_data_without_changing_visibility()
    {
        $this->setting('huseyinfiliz-rewind.default_public', true);
        $this->prepareDatabase(['rw_snaps' => [
            ['id' => 1, 'user_id' => 2, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 999]]), 'is_public' => true, 'generated_at' => '2025-12-31 23:59:59'],
            ['id' => 2, 'user_id' => 3, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 999]]), 'is_public' => false, 'generated_at' => '2025-12-31 23:59:59'],
        ]]);
        $command = new CommandTester($this->app()->getContainer()->make(GenerateCommand::class));
        $this->assertSame(0, $command->execute(['--year' => 2025, '--users' => true, '--regenerate' => true]));
        $public = RewindSnapshot::where('user_id', 2)->where('year', 2025)->firstOrFail();
        $private = RewindSnapshot::where('user_id', 3)->where('year', 2025)->firstOrFail();
        $this->assertTrue($public->is_public);
        $this->assertFalse($private->is_public);
        $this->assertEquals(1, $public->data['post_count']['count']);
        $this->assertEquals(1, $private->data['post_count']['count']);
    }

    #[Test]
    public function upgrading_preserves_the_saved_manual_year()
    {
        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
        $settings->set('huseyinfiliz-rewind.active_year', 2024);
        $migration = require __DIR__.'/../../../migrations/2026_10_26_000000_preserve_manual_rewind_year.php';
        $migration['up']($this->database()->getSchemaBuilder());
        $this->assertEquals(2024, $this->database()->table('settings')->where('key', 'huseyinfiliz-rewind.active_year')->value('value'));
    }
}
