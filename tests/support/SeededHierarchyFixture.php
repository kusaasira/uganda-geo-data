<?php

declare(strict_types=1);

namespace Tests\Support;

use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

/**
 * Independent, reproducible hierarchy generator for property-based tests.
 * Re-run any failure using the seed displayed in the assertion message.
 * No global random state or third-party testing dependency is required.
 */
final class SeededHierarchyFixture
{
    private int $state;

    /** @var array<string, int> */
    private array $ids = [
        'district' => 100,
        'county' => 100,
        'subcounty' => 100,
        'parish' => 100,
        'village' => 100,
    ];

    /** @var array<int, string> */
    private array $names = [
        'Central', 'CENTRAL', 'Centenary', 'North', 'Cen', '12', '123',
        'East', 'École', 'São', 'Nørr', '0', 'Park', 'New Town',
    ];

    public function __construct(int $seed)
    {
        if ($seed < 1 || $seed > 2147483646) {
            throw new \InvalidArgumentException('Property seed must be a positive 31-bit integer');
        }
        $this->state = $seed;
    }

    public function nextInt(int $minimum, int $maximum): int
    {
        if ($minimum < 0 || $maximum < $minimum) {
            throw new \InvalidArgumentException('Invalid generated integer bounds');
        }
        // Park-Miller LCG with bounded 64-bit intermediate. It is deterministic
        // across all supported 64-bit PHP 8.2+ runtimes.
        $this->state = (int) (($this->state * 48271) % 2147483647);

        return $minimum + ($this->state % ($maximum - $minimum + 1));
    }

    /** @return array<int, District> */
    public function generate(): array
    {
        $districts = [];
        for ($d = 0, $dc = $this->nextInt(1, 3); $d < $dc; $d++) {
            $districtId = $this->id('district');
            $counties = [];
            for ($c = 0, $cc = $this->nextInt(1, 3); $c < $cc; $c++) {
                $countyId = $this->id('county');
                $subcounties = [];
                for ($s = 0, $sc = $this->nextInt(1, 3); $s < $sc; $s++) {
                    $subcountyId = $this->id('subcounty');
                    $parishes = [];
                    for ($p = 0, $pc = $this->nextInt(1, 3); $p < $pc; $p++) {
                        $parishId = $this->id('parish');
                        $villages = [];
                        for ($v = 0, $vc = $this->nextInt(1, 4); $v < $vc; $v++) {
                            $villageId = $this->id('village');
                            $villages[$villageId] = new Village($villageId, $this->name(), $parishId);
                        }
                        $parishes[$parishId] = new Parish($parishId, $this->name(), $subcountyId, $villages);
                    }
                    $subcounties[$subcountyId] = new SubCounty(
                        $subcountyId,
                        $this->name(),
                        $countyId,
                        $parishes
                    );
                }
                $counties[$countyId] = new County($countyId, $this->name(), $districtId, $subcounties);
            }
            $districts[$districtId] = new District($districtId, $this->name(), $counties);
        }

        return $districts;
    }

    private function id(string $level): int
    {
        $this->ids[$level] += $this->nextInt(1, 31);

        return $this->ids[$level];
    }

    private function name(): string
    {
        return $this->names[$this->nextInt(0, count($this->names) - 1)];
    }
}
