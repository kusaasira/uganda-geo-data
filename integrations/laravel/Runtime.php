<?php

declare(strict_types=1);

namespace Uganda\Laravel;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\SnapshotCatalog;

final class Runtime
{
    public static function pdo(?string $connection = null): PDO
    {
        $name = $connection ?: config('uganda-geo.connection') ?: config('database.default');
        $pdo = DB::connection((string) $name)->getPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public static function mapping(): MappingDefinition
    {
        $path = config('uganda-geo.mapping_path');
        if (!is_string($path) || !is_file($path)) {
            return MappingDefinition::managed();
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read Uganda Geo mapping configuration');
        }
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Mapping configuration must be a JSON object');
        }
        return new MappingDefinition($data);
    }

    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        $path = config('uganda-geo.snapshot_path');
        $catalog = new SnapshotCatalog();
        return is_string($path) && $path !== '' ? $catalog->fromFile($path) : $catalog->bundled();
    }

    public static function scope(): string
    {
        return (string) (config('uganda-geo.scope') ?: 'default');
    }
}
