<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class ReleaseReadiness
{
    private const GATES = [
        'all_integrated_ci_checks',
        'canonical_conservation',
        'source_provenance_and_currency',
        'parish_geometry_identity_and_topology',
        'gis_format_roundtrip',
        'geometry_redistribution_rights',
        'standalone_and_laravel_consumer_install',
        'snapshot_and_asset_checksums',
        'release_notes_and_migration',
        'maintainer_release_approval',
        'prerelease_tag_install_smoke',
    ];

    /**
     * Review assertions from independently linked evidence, not a replacement
     * for running the underlying checks and obtaining maintainer authorization.
     *
     * @param array<string, mixed> $attestation
     * @return array<string, mixed>
     */
    public function assess(array $attestation, string $actualSha): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/D', $actualSha)) {
            throw new InvalidArgumentException('Expected full Git commit SHA for readiness check');
        }
        $matchesCommit = ($attestation['integration_sha'] ?? null) === $actualSha;
        $attested = [];
        $blockers = [];
        $evidence = $attestation['gates'] ?? null;
        if (!is_array($evidence)) {
            $evidence = [];
        }
        if (($attestation['schema_version'] ?? null) !== 1) {
            $blockers[] = 'schema_version';
        }
        if (array_diff(array_keys($evidence), self::GATES) !== []) {
            $blockers[] = 'unexpected_gate_attestation';
        }
        foreach (self::GATES as $name) {
            $claim = $evidence[$name] ?? null;
            $status = is_array($claim) ? ($claim['status'] ?? null) : null;
            $url = is_array($claim) ? ($claim['evidence_url'] ?? null) : null;
            // A valid URL is an attested evidence POINTER, not evidence
            // that the linked document is accurate or approved.
            $valid = $status === 'attested' && is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && parse_url($url, PHP_URL_SCHEME) === 'https';
            $attested[$name] = $valid;
            if (!$valid) {
                $blockers[] = $name;
            }
        }
        if (!$matchesCommit) {
            $blockers[] = 'integration_sha_match';
        }
        return [
            'integration_sha' => $actualSha,
            'attestation_matches_checkout' => $matchesCommit,
            'gate_attestations' => $attested,
            'blocked_by' => $blockers,
            'status' => $blockers === [] ? 'attestations_supplied_pending_independent_review' : 'blocked',
            'release_ready' => false,
            'limitations' => 'Complete evidence links are unverified attestations. This report never authorizes tagging, merging or publication.',
        ];
    }
}
