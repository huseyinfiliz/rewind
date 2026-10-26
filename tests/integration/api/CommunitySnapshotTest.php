<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommunitySnapshotTest extends TestCase
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
                ['id' => 1, 'title' => 'Discussion One', 'slug' => 'discussion-one', 'user_id' => 2, 'created_at' => '2025-04-01 10:00:00', 'comment_count' => 3, 'first_post_id' => 1, 'last_post_number' => 3],
                ['id' => 2, 'title' => 'Discussion Two', 'slug' => 'discussion-two', 'user_id' => 4, 'created_at' => '2025-05-01 12:00:00', 'comment_count' => 1, 'first_post_id' => 4, 'last_post_number' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-04-01 10:00:00', 'content' => '<t><p>First post content</p></t>'],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 2, 'type' => 'comment', 'created_at' => '2025-04-02 11:00:00', 'content' => '<t><p>Second post content</p></t>'],
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'user_id' => 4, 'type' => 'comment', 'created_at' => '2025-04-03 12:00:00', 'content' => '<t><p>Alice reply</p></t>'],
                ['id' => 4, 'discussion_id' => 2, 'number' => 1, 'user_id' => 4, 'type' => 'comment', 'created_at' => '2025-05-01 12:00:00', 'content' => '<t><p>Alice new discussion</p></t>'],
            ],
        ]);
    }

    #[Test]
    public function member_can_list_community_snapshots()
    {
        $response = $this->send(
            $this->request('GET', '/api/rw-community', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_generate_community_snapshot()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate', ['authenticatedAs' => 1])
        );

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('data', $body);
    }

    #[Test]
    public function non_admin_cannot_generate_community()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function generated_community_has_correct_total_posts()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate', ['authenticatedAs' => 1])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $data = $body['data']['attributes']['data'];

        $this->assertEquals(4, $data['total_posts']['count']);
    }

    #[Test]
    public function generated_community_has_correct_total_discussions()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate', ['authenticatedAs' => 1])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $data = $body['data']['attributes']['data'];

        $this->assertEquals(2, $data['total_discussions']['count']);
    }

    #[Test]
    public function generated_community_has_top_contributors()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate', ['authenticatedAs' => 1])
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $data = $body['data']['attributes']['data'];

        $this->assertArrayHasKey('top_contributors', $data);
        $this->assertNotEmpty($data['top_contributors']['users']);

        // User 2 has 2 posts, user 4 has 2 posts — both should appear
        $userIds = array_column($data['top_contributors']['users'], 'user_id');
        $this->assertContains(2, $userIds);
        $this->assertContains(4, $userIds);
    }

    #[Test]
    public function admin_can_get_generate_steps()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate-steps', ['authenticatedAs' => 1])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('steps', $body);
        $this->assertNotEmpty($body['steps']);
    }

    #[Test]
    public function admin_can_generate_single_step()
    {
        $response = $this->send(
            $this->request('POST', '/api/rw-community/generate-step', [
                'authenticatedAs' => 1,
                'json' => ['step' => 'total_posts', 'year' => 2025],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('data', $body);
    }
}
