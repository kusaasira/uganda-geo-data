<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/resources/sources.json';
try {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Unable to read source registry');
    }
    $registry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($registry) || ($registry['schema_version'] ?? null) !== 1 || !isset($registry['sources']) || !is_array($registry['sources']) || $registry['sources'] === []) {
        throw new RuntimeException('Invalid source registry envelope');
    }
    $ids = [];
    foreach ($registry['sources'] as $index => $source) {
        if (!is_array($source)) {
            throw new RuntimeException("Source $index must be an object");
        }
        foreach (['id', 'kind', 'publisher', 'url', 'reference_label', 'redistribution_status', 'notes'] as $field) {
            if (!isset($source[$field]) || !is_string($source[$field]) || trim($source[$field]) === '') {
                throw new RuntimeException("Source $index has invalid $field");
            }
        }
        if (isset($ids[$source['id']])) {
            throw new RuntimeException("Duplicate source identifier: " . $source['id']);
        }
        $ids[$source['id']] = true;
        if (parse_url($source['url'], PHP_URL_SCHEME) !== 'https' || !filter_var($source['url'], FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Source $index requires an absolute HTTPS URL");
        }
        if (!in_array($source['redistribution_status'], ['unverified', 'permitted', 'restricted'], true)) {
            throw new RuntimeException("Source $index has an unknown redistribution status");
        }
        foreach (['reference_date', 'retrieved_at'] as $dateField) {
            if (array_key_exists($dateField, $source) && $source[$dateField] !== null && (!is_string($source[$dateField]) || strtotime($source[$dateField]) === false)) {
                throw new RuntimeException("Source $index has an invalid $dateField");
            }
        }
    }
    fwrite(STDOUT, 'Source registry valid: ' . count($ids) . " entries\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Source registry invalid: ' . $exception->getMessage() . "\n");
    exit(1);
}
