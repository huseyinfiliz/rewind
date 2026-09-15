<?php

namespace HuseyinFiliz\Rewind\Community;

use Flarum\Extension\ExtensionManager;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;
use Throwable;

class CommunityMetricRegistry
{
    /** @var CommunityMetric[] */
    protected array $metrics = [];

    public function __construct(
        protected ExtensionManager $extensions,
        protected ConnectionInterface $db,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    public function addMetric(CommunityMetric $metric): void
    {
        $this->metrics[$metric->key()] = $metric;
    }

    public function compute(int $year): array
    {
        $result = [];

        foreach ($this->metrics as $metric) {
            if ($metric->requiredExtension() && ! $this->extensions->isEnabled($metric->requiredExtension())) {
                continue;
            }

            try {
                $this->db->beginTransaction();
                $result[$metric->key()] = $metric->calculate($year);
                $this->db->commit();
            } catch (QueryException $e) {
                if ($this->db->transactionLevel() > 0) {
                    $this->db->rollBack();
                }
                $this->logger?->error("Rewind community metric query failed: {$metric->key()}", ['exception' => $e]);
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
                $this->logger?->error("Rewind community metric failed: {$metric->key()}", ['exception' => $e]);
                $fallback = $this->fallbackForKey($metric->key());
                if ($fallback !== null) {
                    $result[$metric->key()] = $fallback;
                }
            }
        }

        return $result;
    }

    /**
     * Get the list of available metric keys (respecting extension availability).
     */
    public function availableKeys(): array
    {
        $keys = [];

        foreach ($this->metrics as $metric) {
            if ($metric->requiredExtension() && ! $this->extensions->isEnabled($metric->requiredExtension())) {
                continue;
            }

            $keys[] = $metric->key();
        }

        return $keys;
    }

    /**
     * Compute a single metric by key.
     */
    public function computeOne(string $key, int $year): ?array
    {
        $metric = $this->metrics[$key] ?? null;

        if (! $metric) {
            return null;
        }

        if ($metric->requiredExtension() && ! $this->extensions->isEnabled($metric->requiredExtension())) {
            return null;
        }

        try {
            $this->db->beginTransaction();
            $result = $metric->calculate($year);
            $this->db->commit();

            return $result;
        } catch (QueryException $e) {
            if ($this->db->transactionLevel() > 0) {
                $this->db->rollBack();
            }
            $this->logger?->error("Rewind community metric query failed: {$metric->key()}", ['exception' => $e]);

            return $this->fallbackForKey($metric->key());
        } catch (Throwable $e) {
            if ($this->db->transactionLevel() > 0) {
                $this->db->rollBack();
            }
            $this->logger?->error("Rewind community metric failed: {$metric->key()}", ['exception' => $e]);

            return $this->fallbackForKey($metric->key());
        }
    }

    protected function fallbackForKey(string $key): ?array
    {
        return match ($key) {
            'top_contributors' => ['users' => []],
            default => null,
        };
    }
}
