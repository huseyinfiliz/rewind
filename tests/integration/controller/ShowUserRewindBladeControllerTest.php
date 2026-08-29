<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind\Tests\integration\controller;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ShowUserRewindBladeControllerTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-rewind');

        $this->setting('huseyinfiliz-rewind.enabled', true);
        $this->setting('huseyinfiliz-rewind.year_render_modes', json_encode(['2025' => 'blade', '2024' => 'blade']));
        $this->setting('huseyinfiliz-rewind.active_year', 2025);

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
                [
                    'id' => 1,
                    'user_id' => 2,
                    'year' => 2025,
                    'is_public' => 1,
                    'data' => json_encode([
                        'post_count' => ['count' => 42],
                        'discussion_count' => ['count' => 5],
                        'active_days' => ['count' => 30],
                        'word_count' => ['count' => 1250],
                        'most_active_month' => ['peak_month' => 4, 'peak_count' => 20],
                        'night_owl' => ['peak_hour' => 22, 'count' => 15, 'is_night_owl' => true],
                    ]),
                    'generated_at' => '2025-12-31 23:59:59',
                ],
                [
                    'id' => 2,
                    'user_id' => 4,
                    'year' => 2025,
                    'is_public' => 0,
                    'data' => json_encode([
                        'post_count' => ['count' => 10],
                    ]),
                    'generated_at' => '2025-12-31 23:59:59',
                ],
            ],
        ]);
    }

    #[Test]
    public function user_can_view_public_rewind_blade_page()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/2/2025', ['authenticatedAs' => 4])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('2025 in Review', $body);
        $this->assertStringContainsString('42', $body);
        $this->assertStringContainsString('normal', $body);
    }

    #[Test]
    public function user_can_view_own_private_rewind_blade_page()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/4/2025', ['authenticatedAs' => 4])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('alice', $body);
        $this->assertStringContainsString('10', $body);
    }

    #[Test]
    public function other_user_cannot_view_private_rewind_blade_page()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/4/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Private Rewind', $body);
    }

    #[Test]
    public function moderator_can_view_private_rewind_blade_page()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/4/2025', ['authenticatedAs' => 3])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('alice', $body);
    }

    #[Test]
    public function returns_404_when_snapshot_does_not_exist()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/2/2024', ['authenticatedAs' => 2])
        );

        $this->assertEquals(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Rewind Not Found', $body);
    }

    #[Test]
    public function returns_400_for_invalid_year_or_id()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/0/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function returns_403_when_rewind_is_disabled()
    {
        $this->setting('huseyinfiliz-rewind.enabled', false);

        $response = $this->send(
            $this->request('GET', '/rewind/view/2/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Rewind is currently disabled', $body);
    }

    #[Test]
    public function slideshow_mode_delegates_to_frontend_document()
    {
        $this->setting('huseyinfiliz-rewind.year_render_modes', json_encode(['2025' => 'slideshow']));

        $response = $this->send(
            $this->request('GET', '/rewind/view/2/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('flarum', strtolower($body));
    }
}
