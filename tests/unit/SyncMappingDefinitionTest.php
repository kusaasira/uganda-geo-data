<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Sync\MappingDefinition;

final class SyncMappingDefinitionTest extends TestCase
{
    #[Test]
    public function supportsAllFiveAdministrativeLevelsInManagedMode(): void
    {
        $mapping = MappingDefinition::managed();
        self::assertSame('managed', $mapping->strategy());
        self::assertSame(['district', 'county', 'subcounty', 'parish', 'village'], array_keys($mapping->levels()));
        self::assertSame(64, strlen($mapping->fingerprint()));
    }

    #[Test]
    public function rejectsSqlInjectionInTableNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MappingDefinition(['strategy' => 'existing', 'levels' => [
            'district' => ['table' => 'districts; DROP TABLE users', 'key' => 'id', 'name' => 'name'],
        ]]);
    }

    #[Test]
    public function rejectsOverlappingMappedColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MappingDefinition(['strategy' => 'existing', 'levels' => [
            'county' => ['table' => 'counties', 'key' => 'id', 'name' => 'id'],
        ]]);
    }
}
