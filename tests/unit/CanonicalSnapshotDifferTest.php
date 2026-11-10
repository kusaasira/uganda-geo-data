<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\CanonicalSnapshotDiffer;

final class CanonicalSnapshotDifferTest extends TestCase
{
    /** @test */
    public function reportsAddsRemovalsRenamesReparentsAndStatusChangesSeparately(): void
    {
        $old = ['schema_version' => 1, 'snapshot_id' => 'before', 'units' => [
            $this->unit('district:1', 'Old name', null),
            $this->unit('district:2', 'Other district', null),
            $this->unit('parish:3', 'Example Parish', 'district:1'),
            $this->unit('village:4', 'Old village', 'parish:3'),
        ]];
        $parish = $this->unit('parish:3', 'Example Parish', 'district:2');
        $parish['lifecycle_status'] = 'disputed';
        $new = ['schema_version' => 1, 'snapshot_id' => 'after', 'units' => [
            $this->unit('village:5', 'New village', 'parish:3'),
            $this->unit('district:1', 'Updated name', null),
            $parish,
            $this->unit('district:2', 'Other district', null),
        ]];
        $diff = (new CanonicalSnapshotDiffer())->diff($old, $new);
        $this->assertSame(['village:5'], $diff['added_ids']);
        $this->assertSame(['village:4'], $diff['absent_ids']);
        $this->assertSame('district:1', $diff['renamed'][0]['id']);
        $this->assertSame('parish:3', $diff['reparented'][0]['id']);
        $this->assertSame('parish:3', $diff['lifecycle_changed'][0]['id']);
        $this->assertSame('review_required', $diff['status']);
    }

    /** @test */
    public function rejectsDuplicateStableIdentifiersInsteadOfHidingDiffs(): void
    {
        $duplicate = $this->unit('district:1', 'X', null);
        $old = ['schema_version' => 1, 'snapshot_id' => 'before', 'units' => [$duplicate, $duplicate]];
        $new = ['schema_version' => 1, 'snapshot_id' => 'after', 'units' => [$duplicate]];
        $this->expectException(InvalidArgumentException::class);
        (new CanonicalSnapshotDiffer())->diff($old, $new);
    }

    /** @test */
    public function ignoresRowOrderingWhenSnapshotContentsAreIdentical(): void
    {
        $a = $this->unit('district:1', 'A', null);
        $b = $this->unit('district:2', 'B', null);
        $diff = (new CanonicalSnapshotDiffer())->diff(
            ['schema_version' => 1, 'snapshot_id' => 'one', 'units' => [$a, $b]],
            ['schema_version' => 1, 'snapshot_id' => 'two', 'units' => [$b, $a]]
        );
        $this->assertSame('unchanged', $diff['status']);
        $this->assertSame(2, $diff['unchanged_count']);
    }

    /** @return array<string, string|null> */
    private function unit(string $id, string $name, ?string $parent): array
    {
        return ['id' => $id, 'name' => $name, 'parent_id' => $parent,
            'lifecycle_status' => 'active', 'source_id' => 'fixture'];
    }
}
