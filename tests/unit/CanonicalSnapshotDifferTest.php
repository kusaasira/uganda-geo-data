<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\CanonicalSnapshotDiffer;

final class CanonicalSnapshotDifferTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function reportsIdentityBasedAdditionsRemovalsAndChanges(): void
    {
        $before = ['snapshot_id' => 'old', 'units' => [
            ['id' => 'parish:a', 'name' => 'Old Name', 'parent_id' => 'subcounty:1', 'level' => 'parish'],
            ['id' => 'parish:b', 'name' => 'Retained?', 'parent_id' => 'subcounty:1', 'level' => 'parish'],
        ]];
        $after = ['snapshot_id' => 'new', 'units' => [
            ['id' => 'parish:c', 'name' => 'Added', 'parent_id' => 'subcounty:2', 'level' => 'parish'],
            ['id' => 'parish:a', 'name' => 'New Name', 'parent_id' => 'subcounty:2', 'level' => 'parish'],
        ]];
        $report = (new CanonicalSnapshotDiffer())->compare($before, $after);
        $this->assertSame(['added' => 1, 'missing_from_next_snapshot' => 1, 'modified' => 1], $report['summary']);
        $this->assertSame('parish:a', $report['changes'][0]['id']);
        $this->assertArrayHasKey('parent_id', $report['changes'][0]['fields']);
        $this->assertArrayHasKey('name', $report['changes'][0]['fields']);
        $this->assertSame('missing_from_next_snapshot', $report['changes'][1]['kind']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ignoresRecordOrderingAndReportsNoChange(): void
    {
        $records = [['id' => 'a', 'name' => 'One'], ['id' => 'b', 'name' => 'Two']];
        $report = (new CanonicalSnapshotDiffer())->compare(
            ['units' => $records],
            ['units' => array_reverse($records)]
        );
        $this->assertSame([], $report['changes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsDuplicateCanonicalIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CanonicalSnapshotDiffer())->compare(
            ['units' => [['id' => 'dup'], ['id' => 'dup']]],
            ['units' => []]
        );
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsNonListUnitStructuresInsteadOfSilentlyDiffingAnObject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CanonicalSnapshotDiffer())->compare(
            ['units' => ['record' => ['id' => 'x']]],
            ['units' => []]
        );
    }
}
