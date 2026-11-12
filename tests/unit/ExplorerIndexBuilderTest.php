<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\ExplorerIndexBuilder;

final class ExplorerIndexBuilderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function retainsDuplicateNamesByDistinctCanonicalIdentity(): void
    {
        $snapshot = ['schema_version' => 1, 'snapshot_id' => 'test-v1', 'source_ids' => ['test-fixture'], 'units' => [
            ['id' => 'parish:2', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:2', 'confidential' => 'omit', 'source_id' => 'test-fixture'],
            ['id' => 'parish:1', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:1', 'source_id' => 'test-fixture'],
            ['id' => 'subcounty:1', 'name' => 'North', 'level' => 'subcounty', 'parent_id' => 'county:1', 'source_id' => 'test-fixture'],
            ['id' => 'subcounty:2', 'name' => 'South', 'level' => 'subcounty', 'parent_id' => 'county:1', 'source_id' => 'test-fixture'],
            ['id' => 'county:1', 'name' => 'County', 'level' => 'county', 'parent_id' => 'district:1', 'source_id' => 'test-fixture'],
            ['id' => 'district:1', 'name' => 'District', 'level' => 'district', 'parent_id' => null, 'source_id' => 'test-fixture'],
        ]];
        $index = (new ExplorerIndexBuilder())->build($snapshot);
        $this->assertSame(6, $index['unit_count']);
        $this->assertSame(['county:1', 'district:1', 'parish:1', 'parish:2', 'subcounty:1', 'subcounty:2'], array_column($index['units'], 'id'));
        $this->assertArrayNotHasKey('confidential', $index['units'][3]);
        $this->assertSame('test-v1', $index['snapshot_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAnEmptyCanonicalDatasetRatherThanPublishingAFalseExplorer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('nonempty canonical units');

        (new ExplorerIndexBuilder())->build([
            'schema_version' => 1,
            'snapshot_id' => 'empty-snapshot',
            'source_ids' => ['test-fixture'],
            'units' => [],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsDuplicateIdsInsteadOfOverwriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or duplicate canonical unit');
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'snapshot_id' => 'test', 'source_ids' => ['test-fixture'], 'units' => [
            ['id' => 'district:1', 'name' => 'First', 'level' => 'district', 'parent_id' => null, 'source_id' => 'test-fixture'],
            ['id' => 'district:1', 'name' => 'Second', 'level' => 'district', 'parent_id' => null, 'source_id' => 'test-fixture'],
        ]]);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAnOrphanRatherThanDisplayingAFalseCompleteHierarchy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing canonical parent');
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'snapshot_id' => 'test', 'source_ids' => ['test-fixture'], 'units' => [
            ['id' => 'parish:1', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:404', 'source_id' => 'test-fixture'],
        ]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsCyclicAdministrativeAncestry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cyclic canonical ancestry');
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'snapshot_id' => 'test', 'source_ids' => ['test-fixture'], 'units' => [
            ['id' => 'a', 'name' => 'A', 'level' => 'parish', 'parent_id' => 'b', 'source_id' => 'test-fixture'],
            ['id' => 'b', 'name' => 'B', 'level' => 'subcounty', 'parent_id' => 'a', 'source_id' => 'test-fixture'],
        ]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAnUnidentifiedSnapshot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'source_ids' => ['test-fixture'], 'units' => []]);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsMalformedOrDuplicateSourceManifests(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or duplicate canonical source');
        (new ExplorerIndexBuilder())->build([
            'schema_version' => 1,
            'snapshot_id' => 'test',
            'source_ids' => ['source', 'source'],
            'units' => [],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAssociativeSourceLists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('indexed lists');
        (new ExplorerIndexBuilder())->build([
            'schema_version' => 1,
            'snapshot_id' => 'test',
            'source_ids' => ['source-id' => 'source'],
            'units' => [],
        ]);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsNonStringSnapshotIdentityBeforeNormalization(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identity must be a string');
        (new ExplorerIndexBuilder())->build([
            'schema_version' => 1,
            'snapshot_id' => ['invalid'],
            'source_ids' => ['test-fixture'],
            'units' => [],
        ]);
    }
}
