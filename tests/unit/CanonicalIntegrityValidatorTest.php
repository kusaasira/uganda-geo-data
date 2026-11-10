<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Data\CanonicalIntegrityValidator;

final class CanonicalIntegrityValidatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/tests/fixtures/canonical-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @test */
    public function acceptsValidSnapshotAndExplicitCounts(): void
    {
        $result = (new CanonicalIntegrityValidator())->validate($this->fixture(), ['district' => 1, 'county' => 1, 'subcounty' => 1, 'parish' => 1, 'village' => 1]);
        $this->assertTrue($result['valid']);
        $this->assertSame(5, $result['total_units']);
    }

    /** @test */
    public function detectsOrphanedParents(): void
    {
        $snapshot = $this->fixture();
        $snapshot['units'][4]['parent_id'] = 'missing:parish';
        $result = (new CanonicalIntegrityValidator())->validate($snapshot);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Unresolved parent', implode(' ', $result['errors']));
    }

    /** @test */
    public function detectsCycles(): void
    {
        $snapshot = $this->fixture();
        $snapshot['units'][0]['parent_id'] = 'fixture:village:1';
        $result = (new CanonicalIntegrityValidator())->validate($snapshot);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Cycle detected', implode(' ', $result['errors']));
    }

    /** @test */
    public function detectsMissingRowsComparedWithOriginalCounts(): void
    {
        $snapshot = $this->fixture();
        array_pop($snapshot['units']);
        $result = (new CanonicalIntegrityValidator())->validate($snapshot, ['district' => 1, 'county' => 1, 'subcounty' => 1, 'parish' => 1, 'village' => 1]);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Record conservation', implode(' ', $result['errors']));
    }

    /** @test */
    public function rejectsRepeatedIdentityEvenWhenNamesMayRepeat(): void
    {
        $snapshot = $this->fixture();
        $snapshot['units'][] = $snapshot['units'][4];
        $result = (new CanonicalIntegrityValidator())->validate($snapshot);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Duplicate administrative unit ID', implode(' ', $result['errors']));
    }
}
