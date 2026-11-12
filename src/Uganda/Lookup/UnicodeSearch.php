<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use LogicException;
use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

/**
 * An opt-in search surface independent of the v2 legacy ASCII indexes.
 * The normalizer is ICU-backed, while edit distances are pure PHP/PCRE.
 *
 * Full indexes are lazy and capped at two level/mode pairs. Parent-scoped
 * searches instead scan the canonical immediate child collection.
 */
final class UnicodeSearch
{
    private const MAX_INDEXES = 2;

    private const MAX_QUERY_GRAPHEMES = 128;

    private const MAX_FUZZY_RESULTS = 100;

    /** @var array<string, array<string, array<int, object>>> */
    private array $namesByIndex = [];

    /** @var array<string, array<int, array<int, string>>> */
    private array $keysByLength = [];

    /** @var array<string, array<int, string>> */
    private array $sortedKeys = [];

    /** @var array<string, array<string, string|null>> */
    private array $histogramSignatures = [];

    /** @var array<int, string> */
    private array $indexOrder = [];

    public function __construct(private readonly LocationIndex $locations)
    {
    }

    /** @return array<int, object> */
    public function byName(
        string $level,
        string $name,
        ?int $parentId = null,
        bool $ignoreDiacritics = false
    ): array {
        self::assertLevel($level);
        $needle = self::normalizeQuery($name, $ignoreDiacritics);

        if ($parentId !== null) {
            $matches = [];
            foreach ($this->scopedChildren($level, $parentId) as $unit) {
                if (UnicodeNameNormalizer::normalize($unit->name(), $ignoreDiacritics) === $needle) {
                    $matches[] = $unit;
                }
            }

            return $matches;
        }

        $token = $this->ensureIndex($level, $ignoreDiacritics);

        return array_values($this->namesByIndex[$token][$needle] ?? []);
    }

    /** @return array<int, object> */
    public function byPrefix(
        string $level,
        string $prefix,
        ?int $parentId = null,
        ?int $limit = null,
        bool $ignoreDiacritics = false
    ): array {
        self::assertLevel($level);
        $needle = self::normalizeQuery($prefix, $ignoreDiacritics);
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('Unicode prefix result limit must be positive');
        }

        if ($parentId !== null) {
            $groups = $this->groupChildren($level, $parentId, $ignoreDiacritics);
            $keys = array_map('strval', array_keys($groups));
            sort($keys, SORT_STRING);

            return self::appendMatchingPrefix($groups, $keys, $needle, $limit);
        }

        $token = $this->ensureIndex($level, $ignoreDiacritics);
        if (!isset($this->sortedKeys[$token])) {
            $keys = array_map('strval', array_keys($this->namesByIndex[$token]));
            sort($keys, SORT_STRING);
            $this->sortedKeys[$token] = $keys;
        }
        $keys = $this->sortedKeys[$token];

        // Lower bound over normalized keys, avoiding a full dictionary scan.
        $low = 0;
        $high = count($keys);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if (strcmp($keys[$middle], $needle) < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return self::appendMatchingPrefix(
            $this->namesByIndex[$token],
            $keys,
            $needle,
            $limit,
            $low
        );
    }

    /**
     * Fuzzy suggestions ranked by ascending edit distance, then normalized
     * name bytes, then original source order for duplicate display names.
     *
     * @return array<int, array{unit: object, distance: int}>
     */
    public function fuzzy(
        string $level,
        string $query,
        ?int $parentId = null,
        int $limit = 10,
        int $maxDistance = 2,
        bool $ignoreDiacritics = false
    ): array {
        self::assertLevel($level);
        if ($limit < 1 || $limit > self::MAX_FUZZY_RESULTS) {
            throw new InvalidArgumentException('Fuzzy result limit must be between 1 and 100');
        }
        if ($maxDistance < 0 || $maxDistance > 3) {
            throw new InvalidArgumentException('Fuzzy edit distance must be between 0 and 3');
        }
        $needle = self::normalizeQuery($query, $ignoreDiacritics);

        if ($maxDistance === 0) {
            return array_map(
                static fn (object $unit): array => ['unit' => $unit, 'distance' => 0],
                array_slice($this->byName($level, $query, $parentId, $ignoreDiacritics), 0, $limit)
            );
        }

        if ($parentId !== null) {
            $groups = $this->groupChildren($level, $parentId, $ignoreDiacritics);
            $buckets = self::lengthBuckets($groups);
        } else {
            $token = $this->ensureIndex($level, $ignoreDiacritics);
            $groups = $this->namesByIndex[$token];
            if (!isset($this->keysByLength[$token])) {
                $this->keysByLength[$token] = self::lengthBuckets($groups);
                foreach (array_keys($groups) as $groupKey) {
                    $key = (string) $groupKey;
                    $this->histogramSignatures[$token][$key] = FuzzyCandidateFilter::signature($key);
                }
            }
            $buckets = $this->keysByLength[$token];
        }

        $length = count(UnicodeNameNormalizer::graphemes($needle));
        $querySignature = FuzzyCandidateFilter::signature($needle);
        $signatures = $parentId === null ? $this->histogramSignatures[$token] : [];
        $candidates = [];
        for ($n = max(0, $length - $maxDistance); $n <= $length + $maxDistance; $n++) {
            foreach ($buckets[$n] ?? [] as $key) {
                $candidateSignature = $signatures[$key] ?? FuzzyCandidateFilter::signature($key);
                if (!FuzzyCandidateFilter::mayMatch($querySignature, $candidateSignature, $maxDistance)) {
                    continue;
                }

                $distance = UnicodeEditDistance::within($needle, $key, $maxDistance);
                if ($distance !== null) {
                    $candidates[] = ['key' => $key, 'distance' => $distance];
                }
            }
        }
        usort($candidates, static fn (array $a, array $b): int => ($a['distance'] <=> $b['distance'])
            ?: strcmp($a['key'], $b['key']));

        $results = [];
        foreach ($candidates as $candidate) {
            foreach ($groups[$candidate['key']] as $unit) {
                $results[] = ['unit' => $unit, 'distance' => $candidate['distance']];
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * @param array<string, array<int, object>> $groups
     * @param array<int, string> $keys
     * @return array<int, object>
     */
    private static function appendMatchingPrefix(
        array $groups,
        array $keys,
        string $prefix,
        ?int $limit,
        int $start = 0
    ): array {
        $results = [];
        for ($i = $start, $count = count($keys); $i < $count; $i++) {
            $key = $keys[$i];
            if (!str_starts_with($key, $prefix)) {
                // Scoped keys are also sorted. Once the prefix range has been
                // reached, lexicographic order guarantees no later match.
                if (strcmp($key, $prefix) >= 0) {
                    break;
                }
                continue;
            }

            foreach ($groups[$key] as $unit) {
                $results[] = $unit;
                if ($limit !== null && count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * @param array<string, array<int, object>> $groups
     * @return array<int, array<int, string>>
     */
    private static function lengthBuckets(array $groups): array
    {
        $buckets = [];
        foreach (array_keys($groups) as $key) {
            $name = (string) $key;
            $buckets[count(UnicodeNameNormalizer::graphemes($name))][] = $name;
        }

        return $buckets;
    }

    /** @return array<string, array<int, object>> */
    private function groupChildren(string $level, int $parentId, bool $ignoreDiacritics): array
    {
        $groups = [];
        foreach ($this->scopedChildren($level, $parentId) as $unit) {
            $key = UnicodeNameNormalizer::normalize($unit->name(), $ignoreDiacritics);
            $groups[$key][] = $unit;
        }

        return $groups;
    }

    /** @return array<int, County|SubCounty|Parish|Village> */
    private function scopedChildren(string $level, int $parentId): array
    {
        if ($level === 'district') {
            return [];
        }

        $children = $this->locations->childrenOf($level, $parentId);
        foreach ($children as $child) {
            if (!$child instanceof County && !$child instanceof SubCounty
                && !$child instanceof Parish && !$child instanceof Village) {
                throw new LogicException('Unexpected administrative child model');
            }
        }

        return $children;
    }

    private function ensureIndex(string $level, bool $ignoreDiacritics): string
    {
        $token = $level . ':' . ($ignoreDiacritics ? 'folded' : 'strict');
        if (isset($this->namesByIndex[$token])) {
            // LRU rotation is bounded by MAX_INDEXES: constant-time in practice.
            $position = array_search($token, $this->indexOrder, true);
            if ($position !== false) {
                unset($this->indexOrder[$position]);
                $this->indexOrder = array_values($this->indexOrder);
                $this->indexOrder[] = $token;
            }

            return $token;
        }

        // No partial index is published if normalization raises an exception.
        $groups = [];
        foreach ($this->locations->iterate($level) as $unit) {
            if (!$unit instanceof District && !$unit instanceof County
                && !$unit instanceof SubCounty && !$unit instanceof Parish
                && !$unit instanceof Village) {
                throw new LogicException('Unexpected administrative location model');
            }
            $key = UnicodeNameNormalizer::normalize($unit->name(), $ignoreDiacritics);
            $groups[$key][] = $unit;
        }

        if (count($this->indexOrder) >= self::MAX_INDEXES) {
            $oldest = array_shift($this->indexOrder);
            unset($this->namesByIndex[$oldest], $this->sortedKeys[$oldest], $this->keysByLength[$oldest]);
        }
        $this->namesByIndex[$token] = $groups;
        $this->indexOrder[] = $token;

        return $token;
    }

    private static function normalizeQuery(string $value, bool $ignoreDiacritics): string
    {
        $key = UnicodeNameNormalizer::normalize($value, $ignoreDiacritics);
        if ($key === '') {
            throw new InvalidArgumentException('A nonempty Unicode search query is required');
        }
        if (count(UnicodeNameNormalizer::graphemes($key)) > self::MAX_QUERY_GRAPHEMES) {
            throw new InvalidArgumentException('Unicode search queries cannot exceed 128 grapheme clusters');
        }

        return $key;
    }

    private static function assertLevel(string $level): void
    {
        if (!in_array($level, ['district', 'county', 'subcounty', 'parish', 'village'], true)) {
            throw new InvalidArgumentException('Unsupported administrative level: ' . $level);
        }
    }
}
