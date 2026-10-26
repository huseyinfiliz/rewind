<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DeleteSnapshotTest extends TestCase
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
    public function owner_cannot_delete_own_snapshot()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/rw-snaps/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function moderator_can_delete()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/rw-snaps/1', ['authenticatedAs' => 3])
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_delete()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/rw-snaps/1', ['authenticatedAs' => 1])
        );

        $this->assertEquals(204, $response->getStatusCode());
    }
}
