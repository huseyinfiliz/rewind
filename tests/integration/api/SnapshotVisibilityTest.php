<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SnapshotVisibilityTest extends TestCase
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
            'rw_snaps' => [
                // Public snapshot for user 2
                ['id' => 1, 'user_id' => 2, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 10], 'discussion_count' => ['count' => 3]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => true],
                // Private snapshot for user 4
                ['id' => 2, 'user_id' => 4, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 5]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => false],
                // Empty snapshot for user 3
                ['id' => 3, 'user_id' => 3, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 0], 'discussion_count' => ['count' => 0]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => true],
            ],
        ]);
    }

    #[Test]
    public function public_snapshot_data_visible_to_anyone()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 4])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertNotNull($body['data']['attributes']['data']);
        $this->assertEquals(10, $body['data']['attributes']['data']['post_count']['count']);
    }

    #[Test]
    public function private_snapshot_is_indistinguishable_from_missing_to_others()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/2', ['authenticatedAs' => 2])
        );

        $this->database()->table('rw_snaps')->where('id', 2)->delete();
        $missing = $this->send(
            $this->request('GET', '/api/rw-snaps/2', ['authenticatedAs' => 2])
        );

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals($missing->getStatusCode(), $response->getStatusCode());
        $this->assertEquals((string) $missing->getBody(), (string) $response->getBody());
    }

    #[Test]
    public function private_snapshots_are_excluded_from_filtered_lists_and_includes()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['user' => 4], 'include' => 'user'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([], $body['data']);
        $this->assertEmpty($body['included'] ?? []);
    }

    #[Test]
    public function owner_can_list_own_private_snapshots()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps', ['authenticatedAs' => 4])
                ->withQueryParams(['filter' => ['user' => 4]])
        );

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(['2'], array_column($body['data'], 'id'));
    }

    #[Test]
    public function guests_cannot_list_or_show_private_snapshots()
    {
        $this->prepareDatabase(['group_permission' => [
            ['group_id' => 2, 'permission' => 'huseyinfiliz-rewind.viewForum'],
        ]]);

        $response = $this->send($this->request('GET', '/api/rw-snaps')->withQueryParams(['filter' => ['user' => 4]]));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], json_decode((string) $response->getBody(), true)['data']);
        $response = $this->send($this->request('GET', '/api/rw-snaps/2'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function private_snapshot_data_visible_to_owner()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/2', ['authenticatedAs' => 4])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertNotNull($body['data']['attributes']['data']);
        $this->assertEquals(5, $body['data']['attributes']['data']['post_count']['count']);
    }

    #[Test]
    public function private_snapshot_data_visible_to_moderator()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/2', ['authenticatedAs' => 3])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertNotNull($body['data']['attributes']['data']);
    }

    #[Test]
    public function isEmpty_field_correct_for_empty_snapshot()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/3', ['authenticatedAs' => 3])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['data']['attributes']['isEmpty']);
    }

    #[Test]
    public function isEmpty_field_correct_for_populated_snapshot()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($body['data']['attributes']['isEmpty']);
    }

    #[Test]
    public function canEdit_true_for_owner()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 2])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['data']['attributes']['canEdit']);
    }

    #[Test]
    public function canEdit_false_for_other_user()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 4])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($body['data']['attributes']['canEdit']);
    }

    #[Test]
    public function canModerate_true_for_moderator()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 3])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['data']['attributes']['canModerate']);
    }

    #[Test]
    public function canModerate_false_for_normal_user()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps/1', ['authenticatedAs' => 2])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($body['data']['attributes']['canModerate']);
    }
}
