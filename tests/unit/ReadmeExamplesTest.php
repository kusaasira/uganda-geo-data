<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

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
        $uganda = new Uganda();
        $district = $uganda->district('Mukono');
        $county = reset($district->counties());
        $this->assertNotFalse($county);
        $this->assertSame($county, $district->county($county->name()));
        $subCounty = reset($county->subCounties());
        $this->assertNotFalse($subCounty);
        $this->assertSame($subCounty, $county->subCounty($subCounty->name()));
        $parish = reset($subCounty->parishes());
        $this->assertNotFalse($parish);
        $this->assertSame($parish, $subCounty->parish($parish->name()));
        $village = reset($parish->villages());
        $this->assertNotFalse($village);
        $this->assertSame($village, $parish->village($village->name()));
    }
}
