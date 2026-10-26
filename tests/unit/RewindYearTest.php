<?php

namespace HuseyinFiliz\Rewind\Tests\unit;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Rewind\RewindYear;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RewindYearTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function automatic_year_rolls_over_at_server_utc_midnight()
    {
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(fn ($key, $default = null) => $key === 'huseyinfiliz-rewind.automatic_year' ? true : $default);
        $year = new RewindYear($settings);

        Carbon::setTestNow('2026-12-31 23:59:59 UTC');
        $this->assertSame(2025, $year->active());
        Carbon::setTestNow('2027-01-01 00:00:00 UTC');
        $this->assertSame(2026, $year->active());
    }

    #[Test]
    public function manual_year_is_preserved_when_automation_is_disabled()
    {
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(fn ($key, $default = null) => match ($key) {
            'huseyinfiliz-rewind.automatic_year' => false,
            'huseyinfiliz-rewind.active_year' => 2024,
            default => $default,
        });
        Carbon::setTestNow('2027-01-01 00:00:00 UTC');
        $this->assertSame(2024, (new RewindYear($settings))->active());
    }
}
