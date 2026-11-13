# Changelog

All notable public API changes, maintenance improvements and source-data releases are recorded here. Package versions use [Semantic Versioning](https://semver.org/). Dataset reference dates and source identities are tracked independently from Composer versions.

## [2.0.2] - Unreleased

**Additive integration capabilities.** Existing PHP 8.2+ geographic lookup interfaces and the bundled legacy administrative snapshot remain unchanged. All database writes require an explicit integration setup and reviewed sync approval.

### Added

- Framework-independent PDO integration for MySQL/MariaDB, PostgreSQL and SQLite.
- Read-only database schema discovery, configurable level-to-table mappings and optional tenant-scoped external identifiers.
- Dedicated source-qualified geography, reviewed local identity crosswalk, synchronization run history and resumable checkpoint tables.
- Canonical source snapshot validation, immutable SHA-256 manifests and portable JSON, NDJSON and CSV exports.
- Explicit dry-run plans with before/after changes, unresolved identity counts and approval-bound execution.
- Single-transaction synchronization and separately opt-in, checkpointed bounded batches with resumable failure recovery.
- Standalone Composer executable and optional Laravel service provider, Artisan setup, inspection, synchronization and status commands.
- Laravel queue integration with approved plan and source artifacts pinned to a private storage disk.
- Real database CI coverage for MySQL 8.4, PostgreSQL 17 and SQLite alongside the existing PHP matrix and standalone/Laravel consumer checks.

### Safety and compatibility

- Composer installation and regular lookups never change a database.
- Existing local primary keys are preserved. Unknown or duplicate names never authorize automatic identity matches.
- Existing target records are never automatically deleted, retired or reassigned.
- Transactional batch checkpoints are atomic per batch, **not across the entire multi-batch run**.
- Tenant-scoped mappings require configured scope columns and caller-side authorization.
- Historical package source IDs are not asserted to be official UBOS identifiers.

## [2.0.1] - 2026-11-12

**Backward-compatible maintenance release.** The existing PHP 8.2+ public API, source IDs and bundled historical dataset remain intact. Advanced Unicode search is available through opt-in methods with optional `ext-intl`.

### Fixed

- Build parent-child adjacency indexes only for the administrative level actually queried, without changing the recorded source identities or child ordering.
- Expose explicit compact JSON location representations without recursively serializing entire district hierarchies. Existing nested `toArray()` output is unchanged.
- Isolate patch release notes from the breaking-change narrative of the v2.0.0 major release.

### Added

- Lazy, binary-search-based name prefix lookups with optional parent identity and result limits.
- Deterministic warm lookup latency summaries, repeatable regressions and inspectable CI benchmark artifacts.
- v2.0.1 release-candidate validation and published-tag verification support.
- Independent active-versus-reserved memory profiling for construction, indexes, caches, streaming and flattened collections.
- Bounded memory-regression verification across PHP 8.2, 8.4 and 8.5, including repeated snapshot construction and release.
- Deterministic seeded hierarchy property tests, mutated dataset rejection, mixed-query cache invariants and standalone adversarial CI.
- Opt-in ICU-backed NFKC casefold name matching, Unicode prefix navigation and accent-insensitive matching without replacing source identities.
- Ranked grapheme-aware fuzzy suggestions with adjacent-transposition handling and explicit edit-distance scores.
- Bounded Unicode dictionaries with histogram-pruned fuzzy candidates and independent multilingual latency benchmarks.
- Strict malformed UTF-8 handling, adversarial query limits and Unicode compatibility verification across PHP 8.2–8.5.

### Changed

- Load administrative ID and name indexes lazily, stream hierarchy traversal and bound lookup caches to reduce unnecessary allocations.
- Apply parent-scoped exact and prefix search fast paths while retaining stable collection order and distinct source identities.
- Guard performance and allocator memory with reproducible cold/warm benchmarks, property-based tests and PHP 8.2–8.5 CI coverage.

### Security and GIS integrity

- Reject malformed UTF-8, invalid GeoJSON coordinate structures and nonfinite position ordinates.
- Reject invalid source IDs and orphaned parent references before accepting administrative or geometry data.
- Publish acquired GIS artifacts through atomic no-overwrite staged writes that refuse existing symlinks.
- Preserve explicit separation between bundled historical data, third-party geometry and approved redistribution rights.

### Compatibility

- Preserve package installation without `ext-intl` or `ext-ctype`. Unicode-aware methods require optional `ext-intl` and fail explicitly if absent.
- Keep Laravel framework integration optional with no new mandatory PHP services.


## [2.0.0] - 2026-11-10

**Major release.** Requires PHP 8.2+ and Composer 2. Version 2 is **not API-compatible with v1.0.0**. See [the migration guide](docs/migration/v1-to-v2.md).

### Added

- Identity-preserving district, county, subcounty, parish and village object hierarchy.
- Level-specific numeric ID lookup, parent-scoped name search, multi-match results and root-to-parent ancestry traversal.
- Canonical administrative snapshot format, provenance-aware importers and deterministic data integrity checks.
- Immutable input inventories, record-conservation reports, data change summaries and identity crosswalk tooling.
- Public endpoint inspection and optional GeoJSON acquisition with captured source URLs, HTTP metadata and SHA-256 hashes.
- Reviewed parish-to-polygon matching, topology diagnostics and spatial containment tools.
- Optional GDAL-backed GeoJSON, GeoPackage and complete Shapefile export with format round-trip verification.
- Self-contained static administrative explorer with explicit source and geometry-coverage disclaimers.
- PHP CI matrix covering PHP 8.2 through 8.5, lowest-dependency verification, PHPStan, ECS and PHPUnit.
- Independent plain PHP/Laravel consumer checks, deterministic release-note generation, software bill of materials and release-readiness evidence tooling.

### Changed

- Public entry point changes from v1's `Uganda\Geo` to v2's `Uganda\Uganda` model API.
- Identity-sensitive collections use source IDs instead of display-name associative keys.
- Ambiguous singular name lookups raise `Uganda\Exceptions\AmbiguousLocationException` rather than selecting an arbitrary record.
- Minimum PHP version increases to 8.2.
- Bundled historical data, official UBOS statistical references and candidate GIS boundaries are documented as distinct sources with their own reference periods.

### Fixed

- Distinct records with identical normalized names are no longer silently overwritten.
- Missing or invalid packaged datasets produce explicit errors rather than apparently legitimate empty responses.
- Duplicate source IDs, broken parent references and incorrect known legacy parent levels are rejected.
- An empty canonical snapshot cannot generate a falsely usable static explorer.
- Composer license metadata reflects MIT and the nonexistent framework auto-discovery provider is no longer advertised.

### Data and geography

- **No automatic replacement of the historical runtime tables is claimed.** Their original capture date and current national completeness remain unverified.
- Official **Uganda Bureau of Statistics (UBOS)** NPHC 2024 and statistical abstract references provide independently sourced comparison baselines. They do not automatically establish that older package IDs correspond to current government codes.
- Publicly reachable third-party parish geometry can be inspected for optional tooling. Publication of a verified national polygon distribution is a **separate source-identity, coverage and rights decision**.
- No unverified third-party shapefiles are included in the source-code release.

### Upgrade and security

- Consumers must replace v1 chainable JSON access with v2 typed model and identity-safe query APIs.
- Review persistent location identifiers and retain source context before migrating stored records.
- Read [v1-to-v2 migration guidance](docs/migration/v1-to-v2.md), [source provenance](docs/data/sources.md) and the [security policy](SECURITY.md).

## [1.0.0] - 2022-04-24

Initial stable release with the historical `Uganda\Geo` chainable API and the original packaged administrative records.

[2.0.1]: https://github.com/kusaasira/uganda-geo-data/releases/tag/v2.0.1
[2.0.0]: https://github.com/kusaasira/uganda-geo-data/releases/tag/v2.0.0
[1.0.0]: https://github.com/kusaasira/uganda-geo-data/releases/tag/1.0.0
