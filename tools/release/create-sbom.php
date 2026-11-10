<?php

declare(strict_types=1);

use Uganda\Release\SoftwareBillOfMaterials;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tools/release/create-sbom.php VERSION OUTPUT.json\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$created = false;
try {
    if (file_exists($argv[2]) || is_link($argv[2]) || !is_dir(dirname($argv[2]))) {
        throw new RuntimeException('SBOM destination must be unused and its parent must exist');
    }
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    $lockPath = $root . '/composer.lock';
    $lock = is_file($lockPath) ? json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR) : null;
    $sbom = (new SoftwareBillOfMaterials())->build($composer, $lock, $argv[1]);
    $data = json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $fp = fopen($argv[2], 'x');
    if ($fp === false) {
        throw new RuntimeException('Unable to create SBOM');
    }
    $created = true;
    try {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $chunk = fwrite($fp, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Incomplete SBOM write');
            }
            $written += $chunk;
        }
    } finally {
        fclose($fp);
    }
    fwrite(STDOUT, "Deterministic runtime SBOM generated\n");
} catch (Throwable $error) {
    if ($created && is_file($argv[2])) {
        unlink($argv[2]);
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
