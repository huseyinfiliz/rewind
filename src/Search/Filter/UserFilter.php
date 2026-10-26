<?php

namespace HuseyinFiliz\Rewind\Search\Filter;

use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;

/**
 * @implements FilterInterface<DatabaseSearchState>
 */
class UserFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'user';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $userId = is_array($value) ? $value[0] : $value;

        $state->getQuery()->where('user_id', $negate ? '!=' : '=', (int) $userId);
    }
}
