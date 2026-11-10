<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class PerformanceBudget
{
    /** @param array<string, mixed> $metrics
     *  @param array<string, int|float> $limits
     *  @return array<int, string>
     */
    public function violations(array $metrics, array $limits): array
    {
        $errors = [];
        foreach (['constructor_ms', 'index_warmup_ms', 'memory_peak_bytes'] as $key) {
            $value = $metrics[$key] ?? null;
            $limit = $limits[$key] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0
                || (!is_int($limit) && !is_float($limit)) || !is_finite((float) $limit) || $limit <= 0) {
                throw new InvalidArgumentException('Invalid performance metric or budget: ' . $key);
            }
            if ($value > $limit) {
                $errors[] = sprintf('%s: measured %s exceeds maximum %s', $key, $value, $limit);
            }
        }

        return $errors;
    }
}
