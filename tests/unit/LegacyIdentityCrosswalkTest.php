<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\LegacyIdentityCrosswalk;

final class LegacyIdentityCrosswalkTest extends TestCase
{
    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'snapshot_id' => 'fixture',
            'units' => [
                ['id' => 'legacy:parish:4', 'level' => 'parish', 'name' => 'Same',
                    'source_record_id' => '4', 'source_id' => 'legacy-bundled', 'parent_id' => 'legacy:subcounty:3'],
                ['id' => 'legacy:parish:5', 'level' => 'parish', 'name' => 'Same',
                    'source_record_id' => '5', 'source_id' => 'legacy-bundled', 'parent_id' => 'legacy:subcounty:3'],
            ],
        ];
    }

    /** @test */
    public function keepsDuplicateNameUnitsSeparateAndUnverified(): void
    {
        $report = (new LegacyIdentityCrosswalk())->build($this->snapshot());
        $this->assertSame(2, $report['total_legacy_units']);
        $this->assertSame(2, $report['unverified_mappings']);
        $this->assertSame('legacy:parish:4', $report['mappings'][0]['legacy_id']);
        $this->assertSame('legacy:parish:5', $report['mappings'][1]['legacy_id']);
        $this->assertNull($report['mappings'][0]['canonical_id']);
        $this->assertNull($report['mappings'][1]['canonical_id']);
    }

    /** @test */
    public function requiresDatedEvidenceBeforeApprovingAnExternalMapping(): void
    {
        $decision = ['legacy_id' => 'legacy:parish:4', 'canonical_id' => 'canonical:parish:x',
            'evidence_url' => 'https://www.ubos.org/example', 'evidence_date' => '2024-12-31',
            'reviewer' => 'human-reviewer'];
        $report = (new LegacyIdentityCrosswalk())->build($this->snapshot(), [$decision]);
        $this->assertSame(1, $report['verified_mappings']);
        $this->assertSame('canonical:parish:x', $report['mappings'][0]['canonical_id']);
        $this->assertSame('unverified', $report['mappings'][1]['verification_status']);
    }

    /** @test */
    public function rejectsUnsupportedMergesInsteadOfAssigningArbitraryIdentity(): void
    {
        $one = ['legacy_id' => 'legacy:parish:4', 'canonical_id' => 'canonical:parish:x',
            'evidence_url' => 'https://www.ubos.org/example', 'evidence_date' => '2024-12-31',
            'reviewer' => 'human-reviewer'];
        $two = $one;
        $two['legacy_id'] = 'legacy:parish:5';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Many-to-one mapping');
        (new LegacyIdentityCrosswalk())->build($this->snapshot(), [$one, $two]);
    }

    /** @test */
    public function rejectsUnverifiedCandidateProof(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LegacyIdentityCrosswalk())->build($this->snapshot(), [[
            'legacy_id' => 'legacy:parish:4', 'canonical_id' => 'official:1',
            'evidence_url' => 'http://unverified.example', 'evidence_date' => '2025-01-01',
            'reviewer' => 'reviewer',
        ]]);
    }
    /** @test */
    public function rejectsMalformedHttpsEvidenceEvenWithTheExpectedPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LegacyIdentityCrosswalk())->build($this->snapshot(), [[
            'legacy_id' => 'legacy:parish:4', 'canonical_id' => 'canonical:parish:1',
            'evidence_url' => 'https://', 'evidence_date' => '2024-12-31',
            'reviewer' => 'human-reviewer',
        ]]);
    }
}
