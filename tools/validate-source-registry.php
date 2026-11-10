<?php

declare(strict_types=1);

use Uganda\Data\SourceRegistryValidator;

require_once dirname(__DIR__) . '/src/Uganda/Data/SourceRegistryValidator.php';

try {
    $path = dirname(__DIR__) . '/resources/sources.json';
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Unable to read source registry');
    }
    $registry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($registry)) {
        throw new RuntimeException('Invalid source registry JSON object');
    }
    $errors = (new SourceRegistryValidator())->errors($registry);
    if ($errors !== []) {
        throw new RuntimeException(implode('; ', $errors));
    }
    fwrite(STDOUT, 'Source registry valid: ' . count($registry['sources']) . " entries\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Source registry invalid: ' . $exception->getMessage() . "\n");
    exit(1);
}
