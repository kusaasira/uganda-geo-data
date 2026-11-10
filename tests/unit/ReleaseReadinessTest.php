<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ReleaseReadiness;

final class ReleaseReadinessTest extends TestCase
{
    private const SHA = 'dddddddddddddddddddddddddddddddddddddddd';

    /** @test */
    public function itRejectsEmptyAttestationsAndUnknownCommitHeads(): void
    {
        $report = (new ReleaseReadiness())->assess(['integration_sha' => 'invalid', 'gates' => []], self::SHA);
        self::assertSame('blocked', $report['status']);
        self::assertContains('integration_sha_match', $report['blocked_by']);
        self::assertFalse($report['release_ready']);
    }

    /** @test */
    public function evenCompleteEvidenceRequiresHumanPublicationApproval(): void
    {
        $gates = [];
        foreach ([
            'all_integrated_ci_checks', 'canonical_conservation', 'source_provenance_and_currency',
            'parish_geometry_identity_and_topology', 'gis_format_roundtrip',
            'geometry_redistribution_rights', 'standalone_and_laravel_consumer_install',
            'snapshot_and_asset_checksums', 'release_notes_and_migration',
            'maintainer_release_approval', 'published_tag_install_smoke',
        ] as $key) {
            $gates[$key] = ['status' => 'verified', 'evidence_url' => 'https://example.org/check'];
        }
        $report = (new ReleaseReadiness())->assess(['integration_sha' => self::SHA, 'gates' => $gates], self::SHA);
        self::assertSame('evidence_complete_pending_human_verification', $report['status']);
        self::assertSame([], $report['blocked_by']);
        self::assertFalse($report['release_ready']);
    }

    /** @test */
    public function invalidCommitHashNeverPasses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseReadiness())->assess([], 'bad');
    }
}
