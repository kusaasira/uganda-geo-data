<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Village;

final class VillageTest extends TestCase
{
    /** @test */
    public function canAccessProperties(): void
    {
        $village = new Village(123, 'new york', 456);
        $this->assertSame(123, $village->id());
        $this->assertSame('New York', $village->name());
        $this->assertSame(456, $village->parishId());
    }

    /** @test */
    public function canConvertToArray(): void
    {
        $village = new Village(123, 'new york', 456);
        $this->assertIsArray($village->toArray());
        $this->assertSame([
            'id' => 123,
            'name' => 'New York',
            'parishId' => 456
        ], $village->toArray());
    }
}
