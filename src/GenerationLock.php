<?php

namespace HuseyinFiliz\Rewind;

use Flarum\Foundation\ValidationException;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use RuntimeException;

class GenerationLock
{
    public function __construct(protected Store $store)
    {
    }

    public function acquire(string $key, int $seconds = 3600): Lock
    {
        if (! $this->store instanceof LockProvider) {
            throw new RuntimeException('Rewind requires a cache store supporting atomic locks.');
        }

        $lock = $this->store->lock('rewind_generating_'.$key, $seconds);
        if (! $lock instanceof Lock) {
            throw new RuntimeException('Rewind requires an ownership-aware Illuminate cache lock.');
        }
        if (! $lock->get()) {
            throw new ValidationException(['rate_limit' => 'Rewind generation is already in progress. Please wait.']);
        }

        return $lock;
    }

    public function assertOwned(Lock $lock): void
    {
        if (! $lock->isOwnedByCurrentProcess()) {
            throw new ValidationException(['rate_limit' => 'The generation lock expired. Please retry.']);
        }
    }
}
