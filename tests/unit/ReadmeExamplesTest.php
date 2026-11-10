<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Uganda;
use Uganda\Village;

final class ReadmeExamplesTest extends TestCase
{
    /** @test */
    public function aDocumentedDistrictAndItsChildrenResolve(): void
    {
        $uganda = new Uganda();
        $district = $uganda->district('Mukono');
        $this->assertSame($district, $uganda->findById('district', $district->id()));
        $this->assertNotEmpty($district->counties());
        $this->assertNotEmpty($district->subCounties());
        $this->assertNotEmpty($district->parishes());
        $this->assertNotEmpty($district->villages());
    }

    /** @test */
    public function eachDocumentedHierarchyMethodTraversesTheRightParentType(): void
    {
        $village = new Village(5, 'Example Village', 4);
        $parish = new Parish(4, 'Example Parish', 3, [5 => $village]);
        $subCounty = new SubCounty(3, 'Example Subcounty', 2, [4 => $parish]);
        $county = new County(2, 'Example County', 1, [3 => $subCounty]);
        $district = new District(1, 'Example District', [2 => $county]);

        $this->assertSame($county, $district->county('Example County'));
        $this->assertSame($subCounty, $county->subCounty('Example Subcounty'));
        $this->assertSame($parish, $subCounty->parish('Example Parish'));
        $this->assertSame($village, $parish->village('Example Village'));
    }
}
