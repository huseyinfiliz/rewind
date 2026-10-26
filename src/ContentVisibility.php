<?php

namespace HuseyinFiliz\Rewind;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared audience for stored recaps, independent of the generating user's privileges.
 */
class ContentVisibility
{
    protected ?User $audience = null;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    protected function audience(): User
    {
        if ($this->audience !== null) {
            return $this->audience;
        }

        if ((bool) $this->settings->get('huseyinfiliz-rewind.public_content_only', true)) {
            return $this->audience = new Guest;
        }

        // An unpersisted, confirmed member with no custom groups or ownership.
        // User::permissions() supplies the built-in guest/member groups.
        $member = new User;
        $member->id = 0;
        $member->is_email_confirmed = true;
        $member->setRelation('groups', new Collection);

        return $this->audience = $member;
    }

    /** @return Builder<Discussion> */
    public function discussions(): Builder
    {
        return Discussion::query()
            ->whereVisibleTo($this->audience())
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false);
    }

    /** @return Builder<Post> */
    public function posts(): Builder
    {
        return Post::query()
            ->whereVisibleTo($this->audience())
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false)
            ->whereHas('discussion', function (Builder $query) {
                $query->whereNull('discussions.hidden_at')->where('discussions.is_private', false);
            });
    }
}
