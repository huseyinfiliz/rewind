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

class ShowCommunityRewindBladeControllerTest extends TestCase
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
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'huseyinfiliz-rewind.viewForum'],
            ],
            'rw_community' => [
                [
                    'id' => 1,
                    'year' => 2025,
                    'data' => json_encode([
                        'new_users' => ['count' => 150],
                        'total_posts' => ['count' => 3200],
                        'total_discussions' => ['count' => 450],
                        'total_words' => ['total_words' => 180000, 'avg_words_per_post' => 56.25],
                        'busiest_month' => ['peak_month' => 6, 'peak_count' => 450],
                        'peak_hour' => ['peak_hour' => 14, 'peak_count' => 320],
                        'top_tag' => ['name' => 'General', 'color' => '#3498db', 'discussion_count' => 88],
                        'top_discussion' => ['id' => 10, 'title' => 'The Big Thread', 'post_count' => 95],
                        'most_active_user' => ['id' => 2, 'username' => 'normal', 'post_count' => 210],
                        'top_contributors' => [
                            'users' => [
                                ['user_id' => 2, 'username' => 'normal', 'post_count' => 210],
                                ['user_id' => 5, 'username' => 'contributor2', 'post_count' => 140],
                            ],
                        ],
                        'most_loved' => [
                            'users' => [
                                ['user_id' => 2, 'username' => 'normal', 'like_count' => 125],
                            ],
                        ],
                        'best_answers_leaderboard' => [
                            'users' => [
                                ['user_id' => 2, 'username' => 'normal', 'answer_count' => 15],
                            ],
                        ],
                        'badge_leaderboard' => [
                            'users' => [
                                ['user_id' => 2, 'username' => 'normal', 'badge_count' => 8],
                            ],
                        ],
                    ]),
                    'generated_at' => '2025-12-31 23:59:59',
                ],
            ],
        ]);
    }

    #[Test]
    public function user_can_view_community_rewind_blade_page()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('2025 Forum Rewind', $body);
        $this->assertStringContainsString('3,200', $body);
        $this->assertStringContainsString('150', $body);
        $this->assertStringContainsString('56.3 words per post', $body);
        $this->assertStringContainsString('88 discussions', $body);
        $this->assertStringContainsString('Top Contributors', $body);
        $this->assertStringContainsString('contributor2', $body);
        $this->assertStringContainsString('Most Loved Member', $body);
        $this->assertStringContainsString('125 likes received', $body);
        $this->assertStringContainsString('Best Answers Leaderboard', $body);
        $this->assertStringContainsString('15 solutions', $body);
        $this->assertStringContainsString('Badge Leaderboard', $body);
        $this->assertStringContainsString('8 badges', $body);
        $this->assertStringNotContainsString('fonts.googleapis.com', $body);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $body);
        $this->assertStringContainsString('/assets/forum.css', $body);
    }

    #[Test]
    public function default_community_view_uses_active_year()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('2025 Forum Rewind', $body);
    }

    #[Test]
    public function returns_404_when_community_snapshot_missing()
    {
        $response = $this->send(
            $this->request('GET', '/rewind/view/2024', ['authenticatedAs' => 2])
        );

        $this->assertEquals(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Community Rewind Not Found', $body);
    }

    #[Test]
    public function returns_403_when_disabled()
    {
        $this->setting('huseyinfiliz-rewind.enabled', false);

        $response = $this->send(
            $this->request('GET', '/rewind/view/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function slideshow_mode_delegates_to_frontend_document()
    {
        $this->setting('huseyinfiliz-rewind.year_render_modes', json_encode(['2025' => 'slideshow']));

        $response = $this->send(
            $this->request('GET', '/rewind/view/2025', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('flarum', strtolower($body));
    }
}
