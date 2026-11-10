<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Exceptions\AmbiguousLocationException;
use Uganda\Uganda;

final class AdministrativeNameSearchTest extends TestCase
{
    /** @test */
    public function returnsAllGlobalNameMatchesInsteadOfChoosingArbitrarily(): void
    {
        $uganda = new Uganda();
        $fixture = $this->repeatedParishFixture();
        $matches = $uganda->searchByName('parish', $fixture['name']);
        $this->assertGreaterThanOrEqual(2, count($matches));

        $this->expectException(AmbiguousAdministrativeNameException::class);
        $uganda->parish($fixture['name']);
    }

    /** @test */
    public function scopesRepeatedParishNamesToTheirActualParent(): void
    {
        $uganda = new Uganda();
        $fixture = $this->repeatedParishFixture();
        $matches = $uganda->searchByName('parish', $fixture['name'], $fixture['parent']);
        $this->assertGreaterThanOrEqual(2, count($matches));
        foreach ($matches as $candidate) {
            $this->assertSame($fixture['parent'], $candidate->subCountyId());
        }
    }

    /** @test */
    public function preservesSameParentParishNameCollisionsForSearch(): void
    {
        $uganda = new Uganda();
        $source = dirname(__DIR__, 2) . '/src/Uganda/Data/parishes.json';
        $rows = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
        $seen = [];
        foreach ($rows as $row) {
            $key = $row['subcounty'] . ':' . strtolower($row['name']);
            if (isset($seen[$key])) {
                $matches = $uganda->searchByName('parish', $row['name'], (int) $row['subcounty']);
                $this->assertGreaterThanOrEqual(2, count($matches));
                return;
            }
            $seen[$key] = true;
        }
        $this->fail('Required same-parent parish name fixture is absent');
    }

    /** @test */
    public function unknownSearchLevelsFailRatherThanReturningMisleadingEmptyResults(): void
    {
        $uganda = new Uganda();
        $this->expectException(InvalidArgumentException::class);
        $uganda->searchByName('continent', 'Africa');
    }

    /** @return array{name: string, parent: int} */
    private function repeatedParishFixture(): array
    {
        $source = dirname(__DIR__, 2) . '/src/Uganda/Data/parishes.json';
        $rows = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
        $seen = [];
        foreach ($rows as $row) {
            $key = $row['subcounty'] . ':' . strtolower($row['name']);
            if (isset($seen[$key])) {
                return ['name' => $row['name'], 'parent' => (int) $row['subcounty']];
            }
            $seen[$key] = true;
        }
        $this->fail('Required same-parent parish name fixture is absent');
    }
}
