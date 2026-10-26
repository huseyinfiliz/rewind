<?php

namespace HuseyinFiliz\Rewind;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Metric\MetricRegistry;
use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;

class UserSnapshotGenerator
{
    public function __construct(
        protected MetricRegistry $metrics,
        protected SettingsRepositoryInterface $settings,
        protected GenerationLock $locks,
    ) {
    }

    public function generate(User $user, int $year, bool $regenerate = true, bool $rateLimit = false): RewindSnapshot
    {
        $lock = $this->locks->acquire('user_'.$user->id);
        try {
            $existing = RewindSnapshot::where('user_id', $user->id)->where('year', $year)->first();
            if ($rateLimit) {
                $latest = RewindSnapshot::where('user_id', $user->id)->orderByDesc('generated_at')->first();
                if ($latest && $latest->generated_at->diffInSeconds(Carbon::now()) < 60) {
                    throw new ValidationException(['rate_limit' => 'Please wait at least 1 minute before generating.']);
                }
            }
            if ($existing && ! $regenerate) {
                throw new PermissionDeniedException;
            }

            $data = $this->metrics->compute($user, $year);
            if ($this->settings->get('huseyinfiliz-rewind.community_comparison_enabled')) {
                $community = CommunitySnapshot::where('year', $year)->first();
                $cd = $community->data ?? [];
                $members = $cd['active_members']['count'] ?? 0;
                // Older snapshots must be regenerated before offering this comparison.
                if ($members > 0) {
                    $data['_community_avg'] = [
                        'posts' => round(($cd['total_posts']['count'] ?? 0) / $members, 1),
                        'discussions' => round(($cd['total_discussions']['count'] ?? 0) / $members, 1),
                        'words' => round(($cd['total_words']['total_words'] ?? 0) / $members),
                    ];
                }
            }

            $this->locks->assertOwned($lock);

            return RewindSnapshot::updateOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                [
                    'data' => $data,
                    'generated_at' => Carbon::now(),
                    'is_public' => $existing ? $existing->is_public : (bool) $this->settings->get('huseyinfiliz-rewind.default_public', false),
                ]
            );
        } finally {
            $lock->release();
        }
    }
}
