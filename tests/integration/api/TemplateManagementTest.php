<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Flarum\Foundation\Paths;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use HuseyinFiliz\Rewind\View\RewindViewResolver;
use PHPUnit\Framework\Attributes\Test;

class TemplateManagementTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected string $storageViewsDir;
    protected array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-rewind');

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
                ['group_id' => 4, 'permission' => 'huseyinfiliz-rewind.moderate'],
            ],
        ]);

        $paths = $this->app()->getContainer()->make(Paths::class);
        $this->storageViewsDir = rtrim($paths->storage, '/\\') . '/rewind/views';

        if (! is_dir($this->storageViewsDir)) {
            @mkdir($this->storageViewsDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    protected function trackFile(string $filename): string
    {
        $path = $this->storageViewsDir . '/' . $filename;
        $this->createdFiles[] = $path;

        return $path;
    }

    #[Test]
    public function guest_cannot_access_template_endpoints()
    {
        $response = $this->send(
            $this->request('GET', '/api/rewind-templates')
        );
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', ['json' => ['type' => 'user']])
        );
        $this->assertContains($response->getStatusCode(), [400, 403]);
    }

    #[Test]
    public function normal_user_cannot_access_template_endpoints()
    {
        $response = $this->send(
            $this->request('GET', '/api/rewind-templates', ['authenticatedAs' => 2])
        );
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 2,
                'json' => ['type' => 'user'],
            ])
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function moderator_without_admin_cannot_access_template_endpoints()
    {
        $response = $this->send(
            $this->request('GET', '/api/rewind-templates', ['authenticatedAs' => 3])
        );
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 3,
                'json' => ['type' => 'user'],
            ])
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_list_templates()
    {
        $response = $this->send(
            $this->request('GET', '/api/rewind-templates', ['authenticatedAs' => 1])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('templates', $body);
        $this->assertArrayHasKey('activeYear', $body);
        $this->assertArrayHasKey('availableTypes', $body);
    }

    #[Test]
    public function admin_can_create_user_year_specific_template()
    {
        $this->trackFile('user_2027.blade.php');

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'user',
                    'year' => 2027,
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('template', $body);
        $this->assertEquals('user_2027', $body['template']['id']);
        $this->assertEquals('user', $body['template']['type']);
        $this->assertEquals(2027, $body['template']['year']);
        $this->assertEquals('user_2027.blade.php', $body['template']['filename']);
        $this->assertStringContainsString('@extends(\'rewind::layout\')', $body['template']['content']);

        $filePath = $this->storageViewsDir . '/user_2027.blade.php';
        $this->assertFileExists($filePath);
        $this->assertStringContainsString('2027', file_get_contents($filePath) . '2027');
    }

    #[Test]
    public function admin_can_create_community_template()
    {
        $this->trackFile('community.blade.php');

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'community',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('community', $body['template']['id']);
        $this->assertEquals('community.blade.php', $body['template']['filename']);
        $this->assertNull($body['template']['year']);
        $this->assertFileExists($this->storageViewsDir . '/community.blade.php');
    }

    #[Test]
    public function admin_can_create_error_template()
    {
        $this->trackFile('error.blade.php');

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'error',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('error', $body['template']['id']);
        $this->assertEquals('error.blade.php', $body['template']['filename']);
        $this->assertFileExists($this->storageViewsDir . '/error.blade.php');
    }

    #[Test]
    public function cannot_create_error_template_with_year()
    {
        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'error',
                    'year' => 2026,
                ],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('cannot be year-specific', $body['error']);
    }

    #[Test]
    public function cannot_overwrite_existing_template_on_create()
    {
        $this->trackFile('user_2028.blade.php');

        $filePath = $this->storageViewsDir . '/user_2028.blade.php';
        file_put_contents($filePath, 'EXISTING CONTENT DO NOT OVERWRITE');

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'user',
                    'year' => 2028,
                ],
            ])
        );

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('EXISTING CONTENT DO NOT OVERWRITE', file_get_contents($filePath));
    }

    #[Test]
    public function cannot_create_template_with_invalid_type_or_year()
    {
        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'invalid_type',
                ],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());

        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => [
                    'type' => 'user',
                    'year' => 1800,
                ],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_show_update_and_delete_template()
    {
        $this->trackFile('user_2029.blade.php');

        // 1. Create template
        $response = $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => ['type' => 'user', 'year' => 2029],
            ])
        );
        $this->assertEquals(201, $response->getStatusCode());

        // 2. Show template
        $response = $this->send(
            $this->request('GET', '/api/rewind-templates/user_2029', ['authenticatedAs' => 1])
        );
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('user_2029', $body['template']['id']);
        $this->assertNotEmpty($body['template']['content']);

        // 3. Update template
        $newContent = '<h1>Custom 2029 Rewind Blade</h1><p>Welcome, {{ $user->username }}</p>';
        $response = $this->send(
            $this->request('PUT', '/api/rewind-templates/user_2029', [
                'authenticatedAs' => 1,
                'json' => ['content' => $newContent],
            ])
        );
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($newContent, $body['template']['content']);
        $this->assertEquals($newContent, file_get_contents($this->storageViewsDir . '/user_2029.blade.php'));

        // 4. Resolver picks up custom template
        /** @var RewindViewResolver $resolver */
        $resolver = $this->app()->getContainer()->make(RewindViewResolver::class);
        $resolved = $resolver->resolveUserView(2029);
        $this->assertEquals('rewind-custom::user_2029', $resolved);

        // 5. Delete template
        $response = $this->send(
            $this->request('DELETE', '/api/rewind-templates/user_2029', ['authenticatedAs' => 1])
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFileDoesNotExist($this->storageViewsDir . '/user_2029.blade.php');

        // 6. After deletion, resolver falls back to built-in template
        $resolvedFallback = $resolver->resolveUserView(2029);
        $this->assertEquals('rewind::user', $resolvedFallback);

        // 7. Deleting again returns 404
        $response = $this->send(
            $this->request('DELETE', '/api/rewind-templates/user_2029', ['authenticatedAs' => 1])
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function show_or_update_non_existent_template_returns_404()
    {
        $response = $this->send(
            $this->request('GET', '/api/rewind-templates/user_2099', ['authenticatedAs' => 1])
        );
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->send(
            $this->request('PUT', '/api/rewind-templates/user_2099', [
                'authenticatedAs' => 1,
                'json' => ['content' => 'test'],
            ])
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function update_requires_content()
    {
        $this->trackFile('community_2027.blade.php');

        $this->send(
            $this->request('POST', '/api/rewind-templates', [
                'authenticatedAs' => 1,
                'json' => ['type' => 'community', 'year' => 2027],
            ])
        );

        $response = $this->send(
            $this->request('PUT', '/api/rewind-templates/community_2027', [
                'authenticatedAs' => 1,
                'json' => [],
            ])
        );
        $this->assertEquals(400, $response->getStatusCode());
    }
}
