<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\ParishBoundaryMatcher;

final class ParishBoundaryMatcherTest extends TestCase
{
    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return ['units' => [
            ['id' => 'parish:1', 'name' => 'Central', 'parent_id' => 'subcounty:1', 'level' => 'parish'],
            ['id' => 'parish:2', 'name' => 'Central', 'parent_id' => 'subcounty:2', 'level' => 'parish'],
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private function features(): array
    {
        return [10 => ['type' => 'Feature'], 11 => ['type' => 'Feature']];
    }

    /** @test */
    public function refusesToAssignPolygonsUsingMatchingNamesAlone(): void
    {
        $report = (new ParishBoundaryMatcher())->match($this->features(), $this->snapshot());
        $this->assertSame(0, $report['matched_count']);
        $this->assertSame('incomplete', $report['status']);
        $this->assertSame(['parish:1', 'parish:2'], $report['unmatched_canonical_unit_ids']);
    }

    /** @test */
    public function joinsOnlyExplicitVerifiedParentScopedMappings(): void
    {
        $reviewed = ['object_id' => 10, 'unit_id' => 'parish:2',
            'parent_unit_id' => 'subcounty:2', 'evidence_url' => 'https://www.ubos.org/example',
            'evidence_date' => '2024-12-31', 'reviewer' => 'human-reviewer'];
        $report = (new ParishBoundaryMatcher())->match($this->features(), $this->snapshot(), [$reviewed]);
        $this->assertSame(1, $report['matched_count']);
        $this->assertSame('parish:2', $report['matches'][0]['unit_id']);
        $this->assertSame(['10'], array_values(array_map('strval', [$report['matches'][0]['source_object_id']])));
    }

    /** @test */
    public function rejectsWrongParentEvidence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent chain mismatch');
        (new ParishBoundaryMatcher())->match($this->features(), $this->snapshot(), [[
            'object_id' => 10, 'unit_id' => 'parish:2', 'parent_unit_id' => 'subcounty:1',
            'evidence_url' => 'https://www.ubos.org/example', 'evidence_date' => '2024-12-31',
            'reviewer' => 'human-reviewer',
        ]]);
    }

    /** @test */
    public function rejectsASecondAssignmentForOneCanonicalUnit(): void
    {
        $one = ['object_id' => 10, 'unit_id' => 'parish:1',
            'parent_unit_id' => 'subcounty:1', 'evidence_url' => 'https://www.ubos.org/example',
            'evidence_date' => '2024-12-31', 'reviewer' => 'human-reviewer'];
        $two = $one;
        $two['object_id'] = 11;
        $this->expectException(InvalidArgumentException::class);
        (new ParishBoundaryMatcher())->match($this->features(), $this->snapshot(), [$one, $two]);
    }
    /** @test */
    public function unmatchedSourceObjectIdsAreConsistentlyStrings(): void
    {
        $report = (new ParishBoundaryMatcher())->match($this->features(), $this->snapshot());
        $this->assertSame(['10', '11'], $report['unmatched_source_object_ids']);
    }

    /** @test */
    public function emptyInputsCannotClaimCompleteGeographicCoverage(): void
    {
        $report = (new ParishBoundaryMatcher())->match([], ['units' => []]);
        $this->assertSame('incomplete', $report['status']);
    }
}
