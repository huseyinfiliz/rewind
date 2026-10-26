<?php

namespace HuseyinFiliz\Rewind\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Community\CommunityMetricRegistry;
use HuseyinFiliz\Rewind\Metric\MetricRegistry;
use PHPUnit\Framework\Attributes\Test;

class ContentPrivacyTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'flarum-likes', 'huseyinfiliz-rewind');
        $this->setting('huseyinfiliz-rewind.enabled', true);
        $this->setting('huseyinfiliz-rewind.active_year', 2025);
        $this->setting('huseyinfiliz-rewind.automatic_year', false);

        $discussions = [];
        $posts = [];
        $likes = [];
        foreach (range(1, 7) as $id) {
            $discussions[] = [
                'id' => $id,
                'title' => $id === 1 ? 'Public discussion' : "Secret discussion {$id}",
                'slug' => "discussion-{$id}",
                'user_id' => 1,
                'created_at' => "2025-0{$id}-01 12:00:00",
                'comment_count' => 1,
                'first_post_id' => $id,
                'hidden_at' => $id === 4 ? '2025-12-01 12:00:00' : null,
                'is_private' => $id === 5,
            ];
            $posts[] = [
                'id' => $id,
                'discussion_id' => $id,
                'number' => 1,
                'user_id' => 1,
                'type' => 'comment',
                'created_at' => "2025-0{$id}-01 12:00:00",
                'content' => $id === 1 ? '<t>publicword publicword</t>' : '<t>secretword secretword</t>',
            ];
            $likes[] = ['post_id' => $id, 'user_id' => 2, 'created_at' => '2025-12-01 12:00:00'];
        }

        // Restricted content would win the "best post" selection without visibility scoping.
        $likes[] = ['post_id' => 2, 'user_id' => 3, 'created_at' => '2025-12-01 12:00:00'];
        $posts[] = [
            'id' => 8, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment',
            'created_at' => '2025-08-01 12:00:00', 'content' => '<t>hiddenword hiddenword</t>',
            'hidden_at' => '2025-12-01 12:00:00',
        ];
        $posts[] = [
            'id' => 9, 'discussion_id' => 1, 'number' => 3, 'user_id' => 1, 'type' => 'comment',
            'created_at' => '2025-09-01 12:00:00', 'content' => '<t>privateword privateword</t>', 'is_private' => true,
        ];

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'staff', 'email' => 'staff@machine.local', 'password' => 'test-password', 'is_email_confirmed' => 1],
            ],
            'group_user' => [['user_id' => 3, 'group_id' => 4]],
            'group_permission' => [
                ['group_id' => 2, 'permission' => 'viewForum'],
                ['group_id' => 3, 'permission' => 'viewForum'],
                ['group_id' => 3, 'permission' => 'tag3.viewForum'],
                ['group_id' => 4, 'permission' => 'tag2.viewForum'],
            ],
            'tags' => [
                ['id' => 1, 'name' => 'Public', 'slug' => 'public', 'is_restricted' => false],
                ['id' => 2, 'name' => 'Staff', 'slug' => 'staff', 'is_restricted' => true],
                ['id' => 3, 'name' => 'Members', 'slug' => 'members', 'is_restricted' => true],
                ['id' => 4, 'name' => 'Staff child', 'slug' => 'staff-child', 'is_restricted' => false, 'parent_id' => 2],
            ],
            'discussions' => $discussions,
            'posts' => $posts,
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 2],
                ['discussion_id' => 3, 'tag_id' => 3],
                ['discussion_id' => 4, 'tag_id' => 1],
                ['discussion_id' => 5, 'tag_id' => 1],
                ['discussion_id' => 6, 'tag_id' => 1],
                ['discussion_id' => 6, 'tag_id' => 2],
                ['discussion_id' => 7, 'tag_id' => 4],
            ],
            'post_likes' => $likes,
        ]);
    }

    #[Test]
    public function admin_personal_metrics_exclude_restricted_private_and_deleted_content_by_default()
    {
        $container = $this->app()->getContainer();
        $data = $container->make(MetricRegistry::class)->compute(User::findOrFail(1), 2025);

        $this->assertEquals(1, $data['post_count']['count']);
        $this->assertEquals(1, $data['discussion_count']['count']);
        $this->assertEquals(1, $data['active_days']['count']);
        $this->assertEquals(2, $data['word_count']['count']);
        $this->assertSame(['publicword'], array_column($data['top_words']['words'], 'word'));
        $this->assertEquals(1, $data['best_post']['post_id']);
        $this->assertEquals(1, $data['top_tag']['count']);
        $this->assertEquals(1, array_sum($data['night_owl']['hour_counts']));
        $this->assertEquals(1, array_sum($data['most_active_month']['months']));
        $this->assertEquals(1, $data['likes_received']['count']);
        $this->assertEquals(1, $data['best_friend']['likes_received']);

        $otherUser = $container->make(MetricRegistry::class)->compute(User::findOrFail(2), 2025);
        $this->assertEquals(1, $otherUser['likes_given']['count']);
    }

    #[Test]
    public function community_metrics_use_the_same_public_audience()
    {
        $data = $this->app()->getContainer()->make(CommunityMetricRegistry::class)->compute(2025);

        $this->assertEquals(1, $data['total_posts']['count']);
        $this->assertEquals(1, $data['total_discussions']['count']);
        $this->assertEquals(2, $data['total_words']['total_words']);
        $this->assertEquals(1, $data['top_discussion']['id']);
        $this->assertStringNotContainsString('secretword', $data['top_discussion']['content_html']);
        $this->assertEquals(1, $data['star_post']['post_id']);
        $this->assertEquals(1, $data['most_active_user']['post_count']);
        $this->assertEquals(1, $data['top_contributors']['users'][0]['post_count']);
        $this->assertEquals(1, $data['most_loved']['users'][0]['like_count']);
        $this->assertEquals(1, $data['top_tag']['discussion_count']);
        $this->assertEquals(1, array_sum($data['peak_hour']['hour_counts']));
        $this->assertEquals(1, $data['busiest_month']['peak_count']);
    }

    #[Test]
    public function member_mode_includes_member_content_but_never_admin_privileges()
    {
        $this->setting('huseyinfiliz-rewind.public_content_only', false);
        $container = $this->app()->getContainer();
        $data = $container->make(MetricRegistry::class)->compute(User::findOrFail(1), 2025);

        $this->assertEquals(2, $data['post_count']['count']);
        $this->assertEquals(2, $data['discussion_count']['count']);
        $this->assertEquals(2, $data['likes_received']['count']);
        $community = $container->make(CommunityMetricRegistry::class)->compute(2025);
        $this->assertEquals(2, $community['total_posts']['count']);
        $this->assertEquals(2, $community['total_discussions']['count']);
        $this->assertEquals(4, $community['total_words']['total_words']);
    }

    #[Test]
    public function members_only_forum_has_no_public_metrics()
    {
        $this->database()->table('group_permission')->where('group_id', 2)->where('permission', 'viewForum')->delete();

        $data = $this->app()->getContainer()->make(CommunityMetricRegistry::class)->compute(2025);
        $this->assertEquals(0, $data['total_posts']['count']);
        $this->assertEquals(0, $data['total_discussions']['count']);
        $this->assertNull($data['top_discussion']['id']);
    }

    #[Test]
    public function member_mode_works_on_a_members_only_forum()
    {
        $this->setting('huseyinfiliz-rewind.public_content_only', false);
        $this->database()->table('group_permission')->where('group_id', 2)->where('permission', 'viewForum')->delete();

        $data = $this->app()->getContainer()->make(CommunityMetricRegistry::class)->compute(2025);
        $this->assertEquals(2, $data['total_posts']['count']);
        $this->assertEquals(2, $data['total_discussions']['count']);
    }
}
