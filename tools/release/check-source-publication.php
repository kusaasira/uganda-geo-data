<?php

declare(strict_types=1);

use Uganda\Data\SourceRegistryValidator;
use Uganda\Release\SourcePublicationGate;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/release/check-source-publication.php registry.json source-id [source-id ...]\n");
    exit(2);
}
try {
    $registry = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($registry)) {
        throw new UnexpectedValueException('Expected source registry object');
    }
    $errors = (new SourceRegistryValidator())->errors($registry);
    if ($errors !== []) {
        throw new RuntimeException('Invalid source registry: ' . implode('; ', $errors));
    }
    (new SourcePublicationGate())->assertMayPublish($registry, array_slice($argv, 2));
    fwrite(STDOUT, "Selected release sources have documented redistribution grants\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
