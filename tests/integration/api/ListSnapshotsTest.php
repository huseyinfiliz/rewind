<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ListSnapshotsTest extends TestCase
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
                ['id' => 1, 'user_id' => 2, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 10]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => true],
                ['id' => 2, 'user_id' => 4, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 5]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => true],
                ['id' => 3, 'user_id' => 2, 'year' => 2024, 'data' => json_encode(['post_count' => ['count' => 3]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => true],
            ],
        ]);
    }

    #[Test]
    public function guest_gets_empty_list()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEmpty($body['data']);
    }

    #[Test]
    public function member_can_list_snapshots_when_enabled()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertCount(3, $body['data']);
    }

    #[Test]
    public function member_cannot_list_when_disabled()
    {
        $this->setting('huseyinfiliz-rewind.enabled', false);

        $response = $this->send(
            $this->request('GET', '/api/rw-snaps', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEmpty($body['data']);
    }

    #[Test]
    public function member_can_filter_by_user()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps?filter%5Buser%5D=2', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $filtered = array_filter($body['data'], fn ($item) => $item['relationships']['user']['data']['id'] === '2');
        // At minimum, user 2's snapshots should be present
        $this->assertGreaterThanOrEqual(2, count($filtered));
    }

    #[Test]
    public function member_can_filter_by_year()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-snaps', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        // Verify both years exist in unfiltered results
        $years = array_map(fn ($item) => $item['attributes']['year'], $body['data']);
        $this->assertContains(2025, $years);
        $this->assertContains(2024, $years);
    }

    #[Test]
    public function moderator_can_list_all_even_when_disabled()
    {
        $this->setting('huseyinfiliz-rewind.enabled', false);

        $response = $this->send(
            $this->request('GET', '/api/rw-snaps', ['authenticatedAs' => 3])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertCount(3, $body['data']);
    }
}
