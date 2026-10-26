<?php

namespace HuseyinFiliz\Rewind\Tests\unit;

use Flarum\Foundation\ValidationException;
use HuseyinFiliz\Rewind\GenerationLock;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GenerationLockTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function generation_remains_locked_after_thirty_seconds()
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $locks = new GenerationLock(new ArrayStore);
        $lock = $locks->acquire('user_2');
        Carbon::setTestNow('2026-01-01 00:01:00');

        try {
            $this->expectException(ValidationException::class);
            $locks->acquire('user_2');
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function expired_owner_cannot_release_a_replacement_lock_or_publish_results()
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $locks = new GenerationLock(new ArrayStore);
        $old = $locks->acquire('community_2025');
        Carbon::setTestNow('2026-01-01 02:00:00');
        $replacement = $locks->acquire('community_2025');

        $this->assertFalse($old->release());
        $this->assertTrue($replacement->isOwnedByCurrentProcess());

        try {
            $this->expectException(ValidationException::class);
            $locks->assertOwned($old);
        } finally {
            $replacement->release();
        }
    }
}
