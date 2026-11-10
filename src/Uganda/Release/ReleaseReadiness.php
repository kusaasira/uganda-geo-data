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
        'published_tag_install_smoke',
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
        $verified = [];
        $blockers = [];
        $evidence = $attestation['gates'] ?? null;
        if (!is_array($evidence)) {
            $evidence = [];
        }
        foreach (self::GATES as $name) {
            $claim = $evidence[$name] ?? null;
            $status = is_array($claim) ? ($claim['status'] ?? null) : null;
            $url = is_array($claim) ? ($claim['evidence_url'] ?? null) : null;
            $valid = $status === 'verified' && is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && parse_url($url, PHP_URL_SCHEME) === 'https';
            $verified[$name] = $valid;
            if (!$valid) {
                $blockers[] = $name;
            }
        }
        if (!$matchesCommit) {
            $blockers[] = 'integration_sha_match';
        }
        return [
            'integration_sha' => $actualSha,
            'commit_verified' => $matchesCommit,
            'gates' => $verified,
            'blocked_by' => $blockers,
            'status' => $blockers === [] ? 'evidence_complete_pending_human_verification' : 'blocked',
            'release_ready' => false,
            'limitations' => 'Links and attestations require independent inspection. This report never authorizes tagging, merging or publication.',
        ];
    }
}
