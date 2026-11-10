<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\PublicationCountComparator;

final class PublicationCountComparatorTest extends TestCase
{
    /** @test */
    public function preservesEachPublicationScopeAndAvoidsInferringLegalChanges(): void
    {
        $old = ['id' => 'abstract-2024', 'counts' => ['parish' => 10717, 'subcounty' => 2197]];
        $new = ['id' => 'bec-2025', 'counts' => [
            'parish_education_scope' => 10867, 'subcounty_education_scope' => 2209
        ]];
        $mappings = [
            ['older' => 'parish', 'newer' => 'parish_education_scope', 'scope_verified' => false],
            ['older' => 'subcounty', 'newer' => 'subcounty_education_scope', 'scope_verified' => false],
        ];
        $report = (new PublicationCountComparator())->compare($old, $new, $mappings);
        $this->assertSame(150, $report['comparisons'][0]['arithmetic_difference']);
        $this->assertSame(12, $report['comparisons'][1]['arithmetic_difference']);
        $this->assertFalse($report['comparisons'][0]['administrative_change_verified']);
        $this->assertSame('not_established', $report['status']);
    }

    /** @test */
    public function rejectsMissingCountsAndInvalidMappingDefinitions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PublicationCountComparator())->compare(
            ['id' => 'one', 'counts' => ['parish' => 5]],
            ['id' => 'two', 'counts' => ['parish_education_scope' => 7]],
            [['older' => 'not_present', 'newer' => 'parish_education_scope', 'scope_verified' => false]]
        );
    }
}
