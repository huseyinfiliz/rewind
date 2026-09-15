<?php

namespace HuseyinFiliz\Rewind\Metric;

use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;
use Throwable;

class MetricRegistry
{
    /** @var RewindMetric[] */
    protected array $metrics = [];

    public function __construct(
        protected ExtensionManager $extensions,
        protected ConnectionInterface $db,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    public function addMetric(RewindMetric $metric): void
    {
        $this->metrics[$metric->key()] = $metric;
    }

    public function compute(User $user, int $year): array
    {
        $result = [];

        foreach ($this->metrics as $metric) {
            if ($metric->requiredExtension() && ! $this->extensions->isEnabled($metric->requiredExtension())) {
                continue;
            }

            try {
                $this->db->beginTransaction();
                $result[$metric->key()] = $metric->calculate($user, $year);
                $this->db->commit();
            } catch (QueryException $e) {
                if ($this->db->transactionLevel() > 0) {
                    $this->db->rollBack();
                }
                $this->logger?->error("Rewind metric query failed: {$metric->key()}", ['exception' => $e]);
                // Some optional ecosystem tables may not exist in every install/test environment.
                // Keep response shape stable for known keys expected by consumers/tests.
                $fallback = $this->fallbackForKey($metric->key());
                if ($fallback !== null) {
                    $result[$metric->key()] = $fallback;
                }
            } catch (Throwable $e) {
                if ($this->db->transactionLevel() > 0) {
                    $this->db->rollBack();
                }
                $this->logger?->error("Rewind metric failed: {$metric->key()}", ['exception' => $e]);

                $fallback = $this->fallbackForKey($metric->key());
                if ($fallback !== null) {
                    $result[$metric->key()] = $fallback;
                }
            }
        }

        return $result;
    }

    protected function fallbackForKey(string $key): ?array
    {
        return match ($key) {
            'best_post' => [
                'post_id' => null,
                'discussion_id' => null,
                'discussion_title' => null,
                'metric_type' => null,
                'count' => 0,
                'content_html' => null,
            ],
            'most_active_month' => [
                'months' => array_fill(1, 12, 0),
                'peak_month' => 0,
                'peak_count' => 0,
            ],
            'night_owl' => [
                'peak_hour' => null,
                'count' => 0,
                'is_night_owl' => false,
                'hour_counts' => array_fill(0, 24, 0),
            ],
            default => null,
        };
    }

    /** @return string[] */
    public function getAvailableKeys(): array
    {
        return array_keys($this->metrics);
    }
}
