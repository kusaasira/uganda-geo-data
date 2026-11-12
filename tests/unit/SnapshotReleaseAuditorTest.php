<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Data\SnapshotReleaseAuditor;

final class SnapshotReleaseAuditorTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function categorizesAdditionsAbsencesAndFieldChangesWithoutConflatingThem(): void
    {
        $before = ['snapshot_id' => 'old', 'units' => [
            ['id' => 'a', 'name' => 'Earlier', 'parent_id' => 'p:1',
                'source_id' => 'source-1', 'lifecycle_status' => 'active'],
            ['id' => 'b', 'name' => 'Missing', 'parent_id' => 'p:1'],
        ]];
        $after = ['snapshot_id' => 'new', 'units' => [
            ['id' => 'a', 'name' => 'Current', 'parent_id' => 'p:2',
                'source_id' => 'source-2', 'lifecycle_status' => 'disputed'],
            ['id' => 'c', 'name' => 'Added', 'parent_id' => 'p:2'],
        ]];

        $report = (new SnapshotReleaseAuditor())->audit($before, $after);
        $this->assertSame('review_required', $report['status']);
        $this->assertSame(
            ['added' => 1, 'missing_from_next_snapshot' => 1, 'modified' => 1],
            $report['summary']
        );
        $this->assertSame('a', $report['groups']['renamed'][0]['id']);
        $this->assertSame('a', $report['groups']['reparented'][0]['id']);
        $this->assertSame('a', $report['groups']['lifecycle_changed'][0]['id']);
        $this->assertSame('a', $report['groups']['provenance_changed'][0]['id']);
        $this->assertSame('Missing', $report['changes'][1]['before']['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returnsAnUnchangedReportForEquivalentSnapshotContent(): void
    {
        $rows = [['id' => 'a', 'name' => 'Same'], ['id' => 'b', 'name' => 'Same']];
        $report = (new SnapshotReleaseAuditor())->audit(
            ['snapshot_id' => 'old', 'units' => $rows],
            ['snapshot_id' => 'new', 'units' => array_reverse($rows)]
        );
        $this->assertSame('unchanged', $report['status']);
        $this->assertSame([], $report['changes']);
    }
}
