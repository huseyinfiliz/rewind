<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UpdateSnapshotTest extends TestCase
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
            ],
        ]);
    }

    #[Test]
    public function owner_can_toggle_public()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/rw-snaps/1', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'type' => 'rw-snaps',
                        'id' => '1',
                        'attributes' => ['isPublic' => false],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($body['data']['attributes']['isPublic']);

        $response = $this->send(
            $this->request('PATCH', '/api/rw-snaps/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'rw-snaps', 'id' => '1', 'attributes' => ['isPublic' => true]]],
            ])
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode((string) $response->getBody(), true)['data']['attributes']['isPublic']);
    }

    #[Test]
    public function other_user_cannot_update()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/rw-snaps/1', [
                'authenticatedAs' => 4,
                'json' => [
                    'data' => [
                        'type' => 'rw-snaps',
                        'id' => '1',
                        'attributes' => ['isPublic' => false],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function guest_cannot_update()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/rw-snaps/1', [
                'json' => [
                    'data' => [
                        'type' => 'rw-snaps',
                        'id' => '1',
                        'attributes' => ['isPublic' => false],
                    ],
                ],
            ])
        );

        $this->assertNotEquals(200, $response->getStatusCode());
    }
}
