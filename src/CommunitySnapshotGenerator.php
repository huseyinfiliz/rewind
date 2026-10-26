<?php

namespace HuseyinFiliz\Rewind;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Rewind\Community\CommunityMetricRegistry;
use HuseyinFiliz\Rewind\Community\Metric\TotalWords;
use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use Illuminate\Contracts\Cache\Repository;

class CommunitySnapshotGenerator
{
    public function __construct(
        protected CommunityMetricRegistry $metrics,
        protected TotalWords $words,
        protected GenerationLock $locks,
        protected Repository $cache,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function begin(int $year): void
    {
        $lock = $this->locks->acquire('community_'.$year, 300);

        try {
            $this->cache->forget($this->draftKey($year));
        } finally {
            $lock->release();
        }
    }

    /** @return array{ready: bool, completed: int, total: int, stepComplete: bool} */
    public function step(int $year, ?string $step = null, bool $regenerate = false): array
    {
        $keys = $this->metrics->availableKeys();
        if ($step !== null && ! in_array($step, $keys, true)) {
            throw new ValidationException(['step' => 'Unknown or unavailable metric.']);
        }

        $lock = $this->locks->acquire('community_'.$year, 300);

        try {
            if (! $regenerate && CommunitySnapshot::where('year', $year)->exists()) {
                return ['ready' => true, 'completed' => count($keys), 'total' => count($keys), 'stepComplete' => true];
            }

            $fingerprint = json_encode([$keys, (bool) $this->settings->get('huseyinfiliz-rewind.public_content_only', true)]);
            $draft = $this->cache->get($this->draftKey($year), []);
            if (($draft['fingerprint'] ?? null) !== $fingerprint) {
                $draft = ['fingerprint' => $fingerprint, 'data' => [], 'words' => ['last_id' => 0, 'words' => 0, 'posts' => 0]];
            }
            $remaining = array_values(array_diff($keys, array_keys($draft['data'])));
            $step ??= $remaining[0] ?? null;
            $stepComplete = true;
            if ($step !== null && ! array_key_exists($step, $draft['data'])) {
                if ($step === 'total_words') {
                    $batch = $this->words->batch($year, $draft['words']['last_id']);
                    $draft['words']['last_id'] = $batch['last_id'];
                    $draft['words']['words'] += $batch['words'];
                    $draft['words']['posts'] += $batch['posts'];
                    $stepComplete = $batch['complete'];
                    if ($stepComplete) {
                        $words = $draft['words']['words'];
                        $posts = $draft['words']['posts'];
                        $draft['data'][$step] = ['total_words' => $words, 'total_posts' => $posts, 'avg_words_per_post' => $posts > 0 ? round($words / $posts, 1) : 0];
                    }
                } else {
                    $result = $this->metrics->computeOne($step, $year);
                    if ($result === null) {
                        throw new ValidationException(['generation' => 'A community metric failed. Please retry or check the server log.']);
                    }
                    $draft['data'][$step] = $result;
                }
            }

            $this->locks->assertOwned($lock);
            $complete = count(array_diff($keys, array_keys($draft['data']))) === 0;
            if ($complete) {
                // Publish once, after every metric is finished. Existing recaps remain intact until then.
                CommunitySnapshot::updateOrCreate(['year' => $year], ['data' => $draft['data'], 'generated_at' => Carbon::now()]);
                $this->cache->forget($this->draftKey($year));
            } else {
                $this->cache->put($this->draftKey($year), $draft, 86400);
            }

            return ['ready' => $complete, 'completed' => count($draft['data']), 'total' => count($keys), 'stepComplete' => $stepComplete];
        } finally {
            $lock->release();
        }
    }

    public function generate(int $year, bool $regenerate = false): CommunitySnapshot
    {
        if ($regenerate) {
            $this->begin($year);
        }
        do {
            $progress = $this->step($year, regenerate: $regenerate);
        } while (! $progress['ready']);

        return CommunitySnapshot::where('year', $year)->firstOrFail();
    }

    protected function draftKey(int $year): string
    {
        return 'rewind_community_draft_'.$year;
    }
}
