<?php

namespace HuseyinFiliz\Rewind;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;

class RewindYear
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function active(): int
    {
        if ($this->settings->get('huseyinfiliz-rewind.automatic_year', true)) {
            return $this->previous();
        }

        return (int) $this->settings->get('huseyinfiliz-rewind.active_year', $this->previous());
    }

    public function previous(): int
    {
        // Flarum stores timestamps in UTC. Never use the visitor's browser clock.
        return Carbon::now('UTC')->year - 1;
    }

    public function __invoke(mixed $value): int
    {
        return $this->active();
    }
}
