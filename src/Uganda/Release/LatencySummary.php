<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class LatencySummary
{
    /**
     * @param array<int, float|int> $samples
     * @return array{count: int, min: float, p50: float, p95: float, p99: float, max: float, mean: float}
     */
    public function summarize(array $samples): array
    {
        if ($samples === []) {
            throw new InvalidArgumentException('Latency samples must not be empty');
        }

        foreach ($samples as $sample) {
            if ((!is_int($sample) && !is_float($sample)) || !is_finite((float) $sample) || $sample < 0) {
                throw new InvalidArgumentException('Latency samples must be finite nonnegative numbers');
            }
        }

        sort($samples, SORT_NUMERIC);
        $count = count($samples);

        return [
            'count' => $count,
            'min' => (float) $samples[0],
            'p50' => $this->percentile($samples, 50),
            'p95' => $this->percentile($samples, 95),
            'p99' => $this->percentile($samples, 99),
            'max' => (float) $samples[$count - 1],
            'mean' => (float) array_sum($samples) / $count,
        ];
    }

    /** @param array<int, float|int> $sorted */
    private function percentile(array $sorted, int $percentage): float
    {
        $position = max(0, (int) ceil(count($sorted) * $percentage / 100) - 1);

        return (float) $sorted[$position];
    }
}
