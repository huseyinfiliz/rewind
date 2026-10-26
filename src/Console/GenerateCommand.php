<?php

namespace HuseyinFiliz\Rewind\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Rewind\CommunitySnapshotGenerator;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use HuseyinFiliz\Rewind\RewindYear;
use HuseyinFiliz\Rewind\UserSnapshotGenerator;
use Symfony\Component\Console\Input\InputOption;

class GenerateCommand extends AbstractCommand
{
    public function __construct(
        protected RewindYear $years,
        protected SettingsRepositoryInterface $settings,
        protected CommunitySnapshotGenerator $community,
        protected UserSnapshotGenerator $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('rewind:generate')->setDescription('Generate or safely refresh Rewind snapshots.')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Recap year (defaults to the active year)')
            ->addOption('users', null, InputOption::VALUE_NONE, 'Generate confirmed users\' personal recaps')
            ->addOption('community', null, InputOption::VALUE_NONE, 'Generate the community recap')
            ->addOption('regenerate', null, InputOption::VALUE_NONE, 'Replace existing data, preserving personal visibility')
            ->addOption('automatic', null, InputOption::VALUE_NONE, 'Scheduler mode: respect automation and enabled settings');
    }

    protected function fire(): int
    {
        $automatic = (bool) $this->input->getOption('automatic');
        if ($automatic && (! $this->settings->get('huseyinfiliz-rewind.enabled') || ! $this->settings->get('huseyinfiliz-rewind.automatic_community'))) {
            return 0;
        }

        $year = $automatic ? $this->years->active() : (int) ($this->input->getOption('year') ?? $this->years->active());
        if ($year < 2000 || $year > $this->years->active()) {
            $this->error('Year must be between 2000 and the active year.');

            return 1;
        }
        if ($automatic && $year > $this->years->previous()) {
            return 0; // Never automatically freeze an unfinished year.
        }

        $regenerate = ! $automatic && (bool) $this->input->getOption('regenerate');
        if ($automatic || $this->input->getOption('community') || ! $this->input->getOption('users')) {
            $this->community->generate($year, $regenerate);
            $this->info("Community Rewind {$year} is ready.");
        }

        if (! $automatic && $this->input->getOption('users')) {
            $query = User::where('is_email_confirmed', true);
            if (! $regenerate) {
                $query->whereNotIn('id', RewindSnapshot::where('year', $year)->select('user_id'));
            }
            $count = 0;
            $query->chunkById(50, function ($users) use ($year, $regenerate, &$count) {
                foreach ($users as $user) {
                    $this->users->generate($user, $year, $regenerate);
                    $count++;
                }
            });
            $this->info("Generated {$count} personal Rewinds for {$year}.");
        }

        return 0;
    }
}
