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
            'maintainer_release_approval', 'prerelease_tag_install_smoke',
        ] as $key) {
            $gates[$key] = ['status' => 'attested', 'evidence_url' => 'https://example.org/check'];
        }
        $report = (new ReleaseReadiness())->assess(['schema_version' => 1, 'integration_sha' => self::SHA, 'gates' => $gates], self::SHA);
        self::assertSame('attestations_supplied_pending_independent_review', $report['status']);
        self::assertTrue($report['attestation_matches_checkout']);
        self::assertTrue($report['gate_attestations']['maintainer_release_approval']);
        self::assertSame([], $report['blocked_by']);
        self::assertFalse($report['release_ready']);
    }

    /** @test */
    public function invalidCommitHashNeverPasses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseReadiness())->assess([], 'bad');
    }
    /** @test */
    public function itRejectsUnknownAttestationGatesRatherThanIgnoringThem(): void
    {
        $report = (new ReleaseReadiness())->assess([
            'schema_version' => 1,
            'integration_sha' => self::SHA,
            'gates' => [
                'unaudited_shortcut' => [
                    'status' => 'attested',
                    'evidence_url' => 'https://example.org/unsafe',
                ],
            ],
        ], self::SHA);
        self::assertContains('unexpected_gate_attestation', $report['blocked_by']);
        self::assertFalse($report['release_ready']);
    }
}
