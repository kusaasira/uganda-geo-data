<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\AdministrativeCountReconciler;

final class AdministrativeCountReconcilerTest extends TestCase
{
    /** @test */
    public function recordsDifferencesWithoutCallingThemMissingUnits(): void
    {
        $snapshot = [
            'snapshot_id' => 'legacy-test',
            'units' => [
                ['id' => 'legacy:district:1', 'level' => 'district'],
                ['id' => 'legacy:parish:2', 'level' => 'parish'],
                ['id' => 'legacy:parish:3', 'level' => 'parish'],
            ],
        ];
        $references = [
            'publications' => [
                ['id' => 'official', 'url' => 'https://example.org/report.pdf',
                    'reference_year' => 2024, 'counts' => ['district' => 3, 'parish_ward' => 5, 'city' => 1]],
            ],
        ];
        $report = (new AdministrativeCountReconciler())->reconcile($snapshot, $references);
        $this->assertSame('aggregate_baseline_only', $report['status']);
        $this->assertSame(3, $report['total_legacy_records']);
        $this->assertSame(-2, $report['reports'][0]['comparisons']['district']['arithmetic_difference']);
        $this->assertSame(-3, $report['reports'][0]['comparisons']['parish_ward']['arithmetic_difference']);
        $this->assertNull($report['reports'][0]['comparisons']['city']['legacy_count']);
        $this->assertFalse($report['reports'][0]['comparisons']['parish_ward']['record_level_match_verified']);
        $this->assertFalse($report['reports'][0]['comparisons']['district']['classification_verified']);
    }

    /** @test */
    public function rejectsMalformedSourceCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AdministrativeCountReconciler())->reconcile(
            ['units' => [['id' => 'one', 'level' => 'parish']]],
            ['publications' => [['id' => 'ref', 'url' => 'https://example.org', 'counts' => ['parish' => -1]]]]
        );
    }
    /** @test */
    public function rejectsDuplicateCanonicalUnitsInsteadOfInflatingCounts(): void
    {
        $unit = ['id' => 'legacy:parish:1', 'level' => 'parish'];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate canonical unit ID');
        (new AdministrativeCountReconciler())->reconcile(
            ['units' => [$unit, $unit]],
            ['publications' => [['id' => 'official', 'url' => 'https://www.ubos.org/',
                'counts' => ['parish' => 2]]]]
        );
    }

    /** @test */
    public function rejectsDuplicatePublicationIds(): void
    {
        $pub = ['id' => 'official', 'url' => 'https://www.ubos.org/', 'counts' => ['parish' => 1]];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate UBOS publication ID');
        (new AdministrativeCountReconciler())->reconcile(
            ['units' => [['id' => 'legacy:parish:1', 'level' => 'parish']]],
            ['publications' => [$pub, $pub]]
        );
    }
}
