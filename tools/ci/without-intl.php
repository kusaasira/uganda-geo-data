<?php

declare(strict_types=1);

// Execute with php -n to prove the plain-PHP library keeps working without
// optional Intl, while Unicode-specific methods fail with a clear message.
if (class_exists(\Normalizer::class, false)) {
    fwrite(STDERR, "No-intl test unexpectedly has ICU normalization available\n");
    exit(2);
}
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Uganda\\')) {
        return;
    }
    $path = dirname(__DIR__, 2) . '/src/Uganda/' . str_replace('\\', '/', substr($class, 7)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$geo = new \Uganda\Uganda();
$districts = $geo->districts();
$district = reset($districts);
if (!$district instanceof \Uganda\District) {
    throw new RuntimeException('Bundled district source is unexpectedly empty');
}
if ($geo->findById('district', $district->id()) !== $district) {
    throw new RuntimeException('Legacy exact ID lookup fails without Intl');
}
if (!in_array($district, $geo->searchByName('district', $district->name()), true)) {
    throw new RuntimeException('Legacy ASCII name lookup fails without Intl');
}

$prefix = strtolower(substr($district->name(), 0, 2));
if (!in_array($district, $geo->searchByPrefix('district', $prefix), true)) {
    throw new RuntimeException('Legacy ASCII prefix lookup fails without Intl');
}

foreach ([
    static fn (): array => $geo->searchByUnicodeName('district', $district->name()),
    static fn (): array => $geo->searchByUnicodePrefix('district', $prefix),
    static fn (): array => $geo->searchFuzzy('district', $district->name()),
] as $search) {
    try {
        $search();
        throw new RuntimeException('Advanced search silently ran without ICU');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'ext-intl')) {
            throw $exception;
        }
    }
}

echo "Legacy search works without Intl; advanced Unicode features fail explicitly\n";
