<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GenerateSnapshotTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-rewind');

        $this->setting('huseyinfiliz-rewind.enabled', true);
        $this->setting('huseyinfiliz-rewind.active_year', 2025);
        $this->setting('huseyinfiliz-rewind.automatic_year', false);

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'mod', 'email' => 'mod@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'alice', 'email' => 'alice@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4],
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.viewForum'],
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.generate'],
                ['group_id' => 4, 'permission' => 'huseyinfiliz-rewind.moderate'],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test Discussion', 'slug' => 'test-discussion', 'user_id' => 2, 'created_at' => '2025-03-15 14:00:00', 'comment_count' => 5, 'first_post_id' => 1, 'last_post_number' => 5],
                ['id' => 2, 'title' => 'Another Topic', 'slug' => 'another-topic', 'user_id' => 4, 'created_at' => '2025-06-10 23:30:00', 'comment_count' => 1, 'first_post_id' => 6, 'last_post_number' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-01-15 03:00:00', 'content' => '<t><p>Hello world testing posts here</p></t>'],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-01-20 04:00:00', 'content' => '<t><p>Another great post with words</p></t>'],
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-03-10 15:00:00', 'content' => '<t><p>March post with content here</p></t>'],
                ['id' => 4, 'discussion_id' => 1, 'number' => 4, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-03-20 16:00:00', 'content' => '<t><p>Second march post forum discussion</p></t>'],
                ['id' => 5, 'discussion_id' => 1, 'number' => 5, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-03-25 02:00:00', 'content' => '<t><p>Late night posting words words words</p></t>'],
                ['id' => 6, 'discussion_id' => 2, 'number' => 1, 'user_id' => 4, 'type' => 'comment', 'created_at' => '2025-06-10 23:30:00', 'content' => '<t><p>Alice post here</p></t>'],
                ['id' => 7, 'discussion_id' => 1, 'number' => 6, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2024-12-31 23:59:00', 'content' => '<t><p>Last year post should not count</p></t>'],
            ],
        ]);
    }

    private function generateAs(int $userId): array
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate', ['authenticatedAs' => $userId])
        );

        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getBody()->getContents(), true),
        ];
    }

    #[Test]
    public function new_snapshots_are_private_by_default()
    {
        $result = $this->generateAs(2);

        $this->assertEquals(201, $result['status']);
        $this->assertFalse($result['body']['data']['attributes']['isPublic']);
        $this->assertEquals(0, $this->database()->table('rw_snaps')->where('user_id', 2)->value('is_public'));
    }

    #[Test]
    public function admin_can_choose_public_default_for_new_snapshots()
    {
        $this->setting('huseyinfiliz-rewind.default_public', true);

        $result = $this->generateAs(2);

        $this->assertEquals(201, $result['status']);
        $this->assertTrue($result['body']['data']['attributes']['isPublic']);
    }

    #[Test]
    public function regeneration_preserves_visibility_despite_changed_default()
    {
        $this->generateAs(3);
        $this->database()->table('rw_snaps')->where('user_id', 3)->update([
            'is_public' => true,
            'generated_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
        ]);

        $result = $this->generateAs(3);

        $this->assertEquals(201, $result['status']);
        $this->assertTrue($result['body']['data']['attributes']['isPublic']);
    }

    #[Test]
    public function guest_cannot_generate()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate')
        );

        $this->assertNotEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function member_without_permission_cannot_generate()
    {
        // User 4 (alice) is a member, but let's remove generate permission
        $this->database()->table('group_permission')
            ->where('permission', 'huseyinfiliz-rewind.generate')
            ->delete();

        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate', ['authenticatedAs' => 4])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function member_can_generate_snapshot()
    {
        $result = $this->generateAs(2);

        $this->assertEquals(201, $result['status']);
        $this->assertArrayHasKey('data', $result['body']);
        $this->assertEquals('rw-snaps', $result['body']['data']['type']);
        $this->assertEquals(2025, $result['body']['data']['attributes']['year']);
    }

    #[Test]
    public function generated_snapshot_has_correct_post_count()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        $this->assertEquals(5, $data['post_count']['count']);
    }

    #[Test]
    public function generated_snapshot_has_correct_discussion_count()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        $this->assertEquals(1, $data['discussion_count']['count']);
    }

    #[Test]
    public function generated_snapshot_has_correct_active_days()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        // Posts on: Jan 15, Jan 20, Mar 10, Mar 20, Mar 25 = 5 distinct days
        $this->assertEquals(5, $data['active_days']['count']);
    }

    #[Test]
    public function generated_snapshot_has_correct_most_active_month()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        // March has 3 posts, January has 2
        $this->assertEquals(3, $data['most_active_month']['peak_month']);
        $this->assertEquals(3, $data['most_active_month']['peak_count']);
        $this->assertEquals(2, $data['most_active_month']['months'][1]); // January
        $this->assertEquals(3, $data['most_active_month']['months'][3]); // March
        $this->assertEquals(0, $data['most_active_month']['months'][6]); // June (no posts from user 2)
    }

    #[Test]
    public function generated_snapshot_has_night_owl_data()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        $this->assertArrayHasKey('night_owl', $data);
        $this->assertArrayHasKey('peak_hour', $data['night_owl']);
        $this->assertArrayHasKey('hour_counts', $data['night_owl']);
        // Posts at hours 2, 3, 4, 15, 16 — 3 of 5 are night hours
        $this->assertGreaterThan(0, $data['night_owl']['count']);
    }

    #[Test]
    public function generated_snapshot_has_word_count()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        $this->assertArrayHasKey('word_count', $data);
        $this->assertGreaterThan(0, $data['word_count']['count']);
    }

    #[Test]
    public function generated_snapshot_excludes_other_year_posts()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        // Post ID 7 is from 2024, should not be counted
        $this->assertEquals(5, $data['post_count']['count']);
    }

    #[Test]
    public function generated_snapshot_excludes_other_users_posts()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        // User 4's post (ID 6) should not appear in user 2's count
        $this->assertEquals(5, $data['post_count']['count']);
    }

    #[Test]
    public function regenerate_blocked_without_moderate_permission()
    {
        // First generate
        $this->generateAs(2);

        // Set generated_at to past to bypass rate limit
        $this->database()->table('rw_snaps')
            ->where('user_id', 2)
            ->update(['generated_at' => Carbon::now()->subMinutes(2)->toDateTimeString()]);

        // Second generate should be blocked (existing snapshot, not moderator)
        $result = $this->generateAs(2);

        $this->assertEquals(403, $result['status']);
    }

    #[Test]
    public function moderator_can_regenerate()
    {
        // First generate for mod
        $first = $this->generateAs(3);
        $this->assertEquals(201, $first['status']);

        // Mod can regenerate (has moderate permission)
        // Need to wait or bypass rate limit — set generated_at to past
        $this->database()->table('rw_snaps')
            ->where('user_id', 3)
            ->update(['generated_at' => Carbon::now()->subMinutes(2)->toDateTimeString()]);

        $second = $this->generateAs(3);
        $this->assertEquals(201, $second['status']);
    }

    #[Test]
    public function rate_limit_prevents_rapid_generation()
    {
        // Generate for mod (mod can regenerate, so only rate limit blocks)
        $first = $this->generateAs(3);
        $this->assertEquals(201, $first['status']);

        // Immediate second attempt should be rate limited
        $second = $this->generateAs(3);
        $this->assertEquals(422, $second['status']);
    }

    #[Test]
    public function disabled_feature_blocks_generation()
    {
        $this->setting('huseyinfiliz-rewind.enabled', false);

        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function generated_snapshot_has_top_words()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        $this->assertArrayHasKey('top_words', $data);
        $this->assertArrayHasKey('words', $data['top_words']);
    }

    #[Test]
    public function generated_snapshot_has_best_post()
    {
        $result = $this->generateAs(2);

        $data = $result['body']['data']['attributes']['data'];
        $this->assertArrayHasKey('best_post', $data);
    }
}
