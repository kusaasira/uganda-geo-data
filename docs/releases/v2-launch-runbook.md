# Uganda Geo v2.0 release operator runbook

This runbook governs publication of the first stable 2.x release. It is a procedure, not evidence that any release has been published or that a candidate is authorized for deployment.

## 1. Establish the reviewed integration revision

1. Freeze a reviewed commit on `next/2.x` and record its 40-character SHA.
2. Confirm that all source-code PRs targeting the integration branch have been reviewed and merged.
3. Review the migration guide and curated release notes against code at the exact integration SHA.
4. Run the full PHP matrix, independent Composer consumer installs, deterministic data build, release security checks, GIS format checks and the release-candidate dry run.
5. Save permanent links to exact workflow runs and corresponding manifests. The CI artifact retention period is not a durable archive.

A green CI result is necessary but does not prove official administrative currency, nationally complete polygon coverage or permission to republish source geometries.

## 2. Verify independent release claims

Treat the following as separate claims, each requiring evidence:

| Claim | Required proof |
| --- | --- |
| Data conservation | Raw input counts, canonical output counts and explicit quarantined records reconcile at every level |
| Administrative authority | Publication-specific unit definitions, as-of dates and source-level identifier crosswalks are independently reviewed |
| Geometry identity | Polygon feature IDs map to reviewed canonical units, with unmatched and ambiguous features reported |
| Topology and coverage | Feature-validity reports, overlap/gap results and a qualified national coverage denominator |
| GIS interoperability | Actual GeoJSON, GeoPackage and complete Shapefile round trips preserve canonical IDs and CRS |
| Publication permission | Asset-level source terms or an explicit redistribution grant plus attribution obligations |
| Consumer compatibility | A clean standalone PHP install, a Laravel consumer smoke and the v1-to-v2 migration tests |
| Artifact integrity | File inventory, source manifest, SHA-256 checksums and version labels match the intended revision |

The default readiness template has intentionally incomplete evidence. Never mark a gate complete based solely on a sample test or a successful data converter.

## 3. Validate prereleases before final merge

1. Generate `v2.0.0-alpha.1` candidate notes using `tools/release/build-notes.php` from `resources/releases/v2-notes.json`. Compare the output against the exact code revision and known limitations.
2. Produce and verify reproducible package and dataset artifact manifests. Keep unverified third-party geometry bytes out of distributable release assets.
3. Create a new immutable prerelease tag only after approval. Never move existing tags.
4. Verify a fresh Composer installation against the exact prerelease version in an independent consumer and test documented APIs.
5. Repeat the process with distinct beta and RC tag names after regressions or compatibility fixes.
6. Record defects, evidence and remediation against a specific candidate SHA. Do not reuse a failed tag.

The candidate-build GitHub Actions workflow is intentionally a read-only dry run. A successful workflow does not itself create a tag or a GitHub release.

## 4. Integrate into main

1. Open the final `next/2.x` → `main` pull request with a self-contained summary, breaking-change review and test evidence.
2. Confirm the final PR runs all required checks **on the main-targeting PR**, including the release-candidate and release-readiness workflows.
3. Verify `mergeable=true`, current source and target SHAs, required approvals, unresolved blockers and source rights relevant to the proposed release contents.
4. Obtain explicit maintainer approval for the merge and stable publication scope.
5. Merge only after the approval. Record the actual post-merge `main` SHA; it may differ from the source branch SHA.

If third-party parish polygons cannot be redistributed lawfully or authoritatively, do not attach them. This limitation must appear in the release notes and in any advertised download list.

## 5. Publish stable from an immutable main revision

1. Build fresh release assets from the approved `main` SHA, never from a mutable floating branch.
2. Generate release notes from the committed manifest and verify migration guidance, PHP support, legal/coverage qualifiers and contributor acknowledgments.
3. Confirm asset hashes and field dictionaries, then create the immutable `v2.0.0` tag pointing at the approved main commit.
4. Publish the GitHub release with the reviewed notes and **only** approved distributable attachments.
5. Confirm Packagist discovers `2.0.0` with the correct PHP constraint, license and autoload metadata.
6. In a new, empty consumer project run `composer require "kusaasira/uganda-geo:^2.0"` and execute the documented v2 API examples.
7. Separately verify each published JSON, CSV, GeoPackage or Shapefile asset in the advertised format, with checksums and identifiers intact.
8. Announce the release only after the stable tag and post-tag consumer tests have passed.

## 6. Failure and recovery

- **Before stable publication:** stop, fix the source in a reviewed PR and create a new alpha, beta or RC tag. Never rewrite a published prerelease.
- **After stable publication:** keep the published tag immutable, disclose material limitations and prepare a new patch or major release according to compatibility impact.
- **Source-rights uncertainty:** omit the affected geometry asset and explain the exclusion; do not turn public endpoint reachability into a license grant.
- **Conflicting official totals:** preserve both dated references, explain classification differences and defer canonical status changes until supported by unit-level evidence.
- **Packagist mismatch:** stop the release announcement until the tagged package installs with the expected API. Correct through a new version rather than force-retagging.
