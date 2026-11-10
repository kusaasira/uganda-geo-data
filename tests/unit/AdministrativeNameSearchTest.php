<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Exceptions\AmbiguousAdministrativeNameException;
use Uganda\Uganda;

final class AdministrativeNameSearchTest extends TestCase
{
    /** @test */
    public function returnsAllGlobalNameMatchesInsteadOfChoosingArbitrarily(): void
    {
        $uganda = new Uganda();
        $matches = $uganda->searchByName('county', 'Lower Madi');
        $this->assertGreaterThanOrEqual(2, count($matches));
        $this->assertNotSame($matches[0]->id(), $matches[1]->id());

        $this->expectException(AmbiguousAdministrativeNameException::class);
        $uganda->county('Lower Madi');
    }

    /** @test */
    public function scopesRepeatedCountyNamesToTheirActualParents(): void
    {
        $uganda = new Uganda();
        $matches = $uganda->searchByName('county', 'Lower Madi');
        $this->assertNotEmpty($matches);
        $county = $matches[0];
        $scoped = $uganda->searchByName('county', 'lower madi', $county->districtId());
        $this->assertContains($county, $scoped);
        foreach ($scoped as $candidate) {
            $this->assertSame($county->districtId(), $candidate->districtId());
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
}
