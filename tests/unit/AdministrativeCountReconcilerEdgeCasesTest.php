<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\AdministrativeCountReconciler;

final class AdministrativeCountReconcilerEdgeCasesTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function neverSilentlyDiscardSameNameRowsFromAggregateTotals(): void
    {
        $snapshot = ['snapshot_id' => 'fixture', 'units' => [
            ['id' => 'legacy:parish:1', 'level' => 'parish', 'name' => 'Example'],
            ['id' => 'legacy:parish:2', 'level' => 'parish', 'name' => 'Example'],
        ]];
        $baseline = ['publications' => [['id' => 'official-reference', 'url' => 'https://example.org',
            'reference_year' => 2024, 'counts' => ['parish' => 2]]]];
        $result = (new AdministrativeCountReconciler())->reconcile($snapshot, $baseline);
        $this->assertSame(2, $result['total_legacy_records']);
        $this->assertSame(2, $result['reports'][0]['comparisons']['parish']['legacy_count']);
        $this->assertSame(0, $result['reports'][0]['comparisons']['parish']['arithmetic_difference']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsBrokenCanonicalSnapshotRows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AdministrativeCountReconciler())->reconcile(
            ['units' => [['id' => 'legacy:parish:1']]],
            ['publications' => [['id' => 'official', 'url' => 'https://example.org',
                'counts' => ['parish' => 1]]]]
        );
    }
}
