<?php

declare(strict_types=1);

use Uganda\Data\PublicationCountComparator;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Data/PublicationCountComparator.php';

try {
    $olderJson = file_get_contents($root . '/resources/data/ubos-2024-reference.json');
    $newJson = file_get_contents($root . '/resources/data/ubos-2025-reference.json');
    if ($olderJson === false || $newJson === false) {
        throw new RuntimeException('One or more UBOS publication manifests are missing');
    }
    $older = json_decode($olderJson, true, 512, JSON_THROW_ON_ERROR);
    $newer = json_decode($newJson, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($older) || !is_array($newer)) {
        throw new RuntimeException('Reference manifests must be JSON objects');
    }
    $abstract = null;
    foreach ($older['publications'] ?? [] as $publication) {
        if (($publication['id'] ?? '') === 'ubos-abstract-2024') {
            $abstract = $publication;
            break;
        }
    }
    if (!is_array($abstract)) {
        throw new RuntimeException('The 2024 annual abstract reference is missing');
    }
    $comparison = (new PublicationCountComparator())->compare($abstract, $newer, [
        ['older' => 'parish', 'newer' => 'parish_education_scope', 'scope_verified' => false],
        ['older' => 'subcounty', 'newer' => 'subcounty_education_scope', 'scope_verified' => false],
    ]);
    fwrite(STDOUT, json_encode($comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Reference review failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
