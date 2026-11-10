<?php

declare(strict_types=1);

namespace Uganda\Data;

use DateTimeImmutable;

final class SourceRegistryValidator
{
    /**
     * @param array<string, mixed> $registry
     * @return array<int, string>
     */
    public function errors(array $registry): array
    {
        $errors = [];
        if (($registry['schema_version'] ?? null) !== 1 ||
            !isset($registry['catalogue_id']) || !is_string($registry['catalogue_id']) ||
            trim($registry['catalogue_id']) === '') {
            $errors[] = 'Invalid source registry envelope';
        }
        $sources = $registry['sources'] ?? null;
        if (!is_array($sources) || $sources === [] ||
            array_keys($sources) !== array_keys(array_values($sources))) {
            $errors[] = 'Source registry requires a nonempty list';
            return $errors;
        }

        $ids = [];
        foreach ($sources as $index => $source) {
            if (!is_array($source)) {
                $errors[] = "Source $index must be an object";
                continue;
            }
            foreach (['id', 'kind', 'publisher', 'url', 'reference_label', 'redistribution_status', 'notes'] as $field) {
                if (!isset($source[$field]) || !is_string($source[$field]) ||
                    trim($source[$field]) === '') {
                    $errors[] = "Source $index has invalid $field";
                }
            }
            $id = $source['id'] ?? null;
            if (is_string($id) && $id !== '') {
                if (isset($ids[$id])) {
                    $errors[] = "Duplicate source identifier: $id";
                }
                $ids[$id] = true;
            }
            $url = $source['url'] ?? null;
            if (!is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' ||
                filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors[] = "Source $index requires an absolute HTTPS URL";
            }
            $status = $source['redistribution_status'] ?? null;
            if (!in_array($status, ['unverified', 'permitted', 'restricted'], true)) {
                $errors[] = "Source $index has an unknown redistribution status";
            }
            if ($status === 'permitted') {
                $license = $source['license_url'] ?? null;
                $credit = $source['attribution'] ?? null;
                if (!is_string($license) || parse_url($license, PHP_URL_SCHEME) !== 'https' ||
                    filter_var($license, FILTER_VALIDATE_URL) === false ||
                    !is_string($credit) || trim($credit) === '') {
                    $errors[] = "Source $index requires license URL and attribution before redistribution is permitted";
                }
            }

            $referenceDate = $source['reference_date'] ?? null;
            if ($referenceDate !== null && (!is_string($referenceDate) || !self::dateMatches('!Y-m-d', $referenceDate))) {
                $errors[] = "Source $index has an invalid reference_date";
            }
            $retrieved = $source['retrieved_at'] ?? null;
            if ($retrieved !== null && (!is_string($retrieved) || !self::dateMatches('!Y-m-d\\TH:i:s\\Z', $retrieved))) {
                $errors[] = "Source $index has an invalid retrieved_at";
            }
        }

        return $errors;
    }

    private static function dateMatches(string $format, string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat($format, $value);

        return $parsed !== false && $parsed->format(substr($format, 1)) === $value;
    }
}
