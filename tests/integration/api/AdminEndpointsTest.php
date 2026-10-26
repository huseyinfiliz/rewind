<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AdminEndpointsTest extends TestCase
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
                ['id' => 1, 'title' => 'Test', 'slug' => 'test', 'user_id' => 2, 'created_at' => '2025-06-01 12:00:00', 'comment_count' => 1, 'first_post_id' => 1, 'last_post_number' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-06-01 12:00:00', 'content' => '<t><p>Test post</p></t>'],
            ],
            'rw_snaps' => [
                ['id' => 1, 'user_id' => 2, 'year' => 2025, 'data' => json_encode(['post_count' => ['count' => 1]]), 'generated_at' => Carbon::now()->toDateTimeString(), 'is_public' => true],
            ],
        ]);
    }

    #[Test]
    public function admin_can_generate_for_user()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate-for-user', [
                'authenticatedAs' => 1,
                'json' => ['userId' => 4, 'year' => 2025],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('rw-snaps', $body['data']['type']);
        $this->assertEquals(0, $this->database()->table('rw_snaps')->where('user_id', 4)->value('is_public'));
    }

    #[Test]
    public function admin_generation_respects_public_default_and_preserves_private_choice()
    {
        $this->setting('huseyinfiliz-rewind.default_public', true);
        $request = fn () => $this->request('POST', '/api/rw-snaps/generate-for-user', [
            'authenticatedAs' => 1,
            'json' => ['userId' => 4, 'year' => 2025],
        ]);

        $this->assertEquals(200, $this->send($request())->getStatusCode());
        $this->assertEquals(1, $this->database()->table('rw_snaps')->where('user_id', 4)->value('is_public'));

        $this->database()->table('rw_snaps')->where('user_id', 4)->update(['is_public' => false]);
        $this->assertEquals(200, $this->send($request())->getStatusCode());
        $this->assertEquals(0, $this->database()->table('rw_snaps')->where('user_id', 4)->value('is_public'));
    }

    #[Test]
    public function non_admin_cannot_generate_for_user()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate-for-user', [
                'authenticatedAs' => 3,
                'json' => ['userId' => 4, 'year' => 2025],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function generate_for_user_requires_valid_user_id()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/generate-for-user', [
                'authenticatedAs' => 1,
                'json' => ['userId' => 0],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_get_missing_users()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/missing-users', [
                'authenticatedAs' => 1,
                'json' => ['year' => 2025],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('users', $body);

        // User 2 already has a snapshot, so users 3 and 4 should be missing
        $missingIds = array_column($body['users'], 'id');
        $this->assertContains(3, $missingIds);
        $this->assertContains(4, $missingIds);
        $this->assertNotContains(2, $missingIds);
    }

    #[Test]
    public function admin_can_select_existing_snapshots_for_safe_refresh()
    {
        $response = $this->send($this->request('POST', '/api/rw-snaps/missing-users', [
            'authenticatedAs' => 1, 'json' => ['year' => 2025, 'regenerate' => true],
        ]));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([2], array_column(json_decode((string) $response->getBody(), true)['users'], 'id'));
    }

    #[Test]
    public function admin_can_get_year_stats()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/year-stats', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('years', $body);
        $this->assertNotEmpty($body['years']);
        $this->assertEquals(2025, $body['years'][0]['year']);
        $this->assertEquals(1, $body['years'][0]['count']);
    }

    #[Test]
    public function admin_can_get_groups()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/groups', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('groups', $body);
        $this->assertNotEmpty($body['groups']);
    }

    #[Test]
    public function admin_can_batch_delete()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/batch-delete', [
                'authenticatedAs' => 1,
                'json' => ['year' => 2025],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals(1, $body['deleted']);
    }

    #[Test]
    public function batch_delete_validates_year()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-snaps/batch-delete', [
                'authenticatedAs' => 1,
                'json' => ['year' => 0],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }
}
