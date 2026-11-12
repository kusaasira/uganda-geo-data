# Uganda Geo v2 public API reference

The stable **v2.0.0** package requires PHP 8.2+. It is not API-compatible with the historical v1.0.0 release. See the migration guide before upgrading existing consumers.

## Installation

Install the published v2 stable release:

    composer require "kusaasira/uganda-geo:^2.0"

For a repository checkout, install Composer dependencies and execute
the independently tested example:

    php examples/standalone-v2.php

## Find and traverse locations

`Uganda\Uganda` loads the bundled legacy administrative hierarchy.
IDs are package source IDs. They must not be represented as verified
official UBOS identifiers.

- `districts(): array<int,District>` returns the district collection.
- `district(string $name): District` resolves a uniquely named district.
- `findById(string $level, int $id): ?object` resolves a level-specific ID.
- `searchByName(string $level, string $name, ?int $parentId = null): array`
  returns every matching unit in the selected level and optional parent.
- `searchByPrefix(string $level, string $prefix, ?int $parentId = null, ?int $limit = null): array` uses a lazily sorted name index for deterministic, parent-scoped prefix matches. Added in v2.0.1; an empty prefix and a nonpositive limit are rejected.
- `childrenOf(string $childLevel, int $parentId): array` returns immediate
  children at the specified level.
- `ancestorsOf(string $level, int $id): array` returns root-to-parent ancestry
  for an existing unit.

Supported levels are `district`, `county`, `subcounty`, `parish` and
`village`. Keep the parent ID together with the child level when calling
`childrenOf`; a numeric ID alone does not identify its administrative type.

## Unicode and fuzzy location queries (unreleased v2.0.1)

The following opt-in methods are available from `Uganda\Uganda` in the v2.0.1 release candidate. They require PHP `ext-intl`, which is **suggested but not required** by Composer. Legacy exact and prefix methods remain fully available without ICU.

| Method | Return contract |
| --- | --- |
| `searchByUnicodeName(string $level, string $name, ?int $parentId = null, bool $ignoreDiacritics = false): array` | All models with the same Unicode-normalized name |
| `searchByUnicodePrefix(string $level, string $prefix, ?int $parentId = null, ?int $limit = null, bool $ignoreDiacritics = false): array` | Matching models in deterministic normalized key order |
| `searchFuzzy(string $level, string $query, ?int $parentId = null, int $limit = 10, int $maxDistance = 2, bool $ignoreDiacritics = false): array` | Original models ordered by edit distance and normalized name |
| `searchFuzzyWithScores(...)` | `array<int, array{unit: object, distance: int}>` with the same ordering |

Unicode matching uses ICU NFKC case folding. Setting `ignoreDiacritics` removes combining marks but may create additional ambiguities, which the API preserves rather than guessing at an ID.

Fuzzy distance uses extended grapheme clusters, not UTF-8 bytes. Insertion, deletion, substitution and adjacent transposition each cost one edit. The algorithm is optimal-string-alignment distance. The accepted edit bound is 0–3 and the fuzzy result limit is 1–100. Queries beyond 128 grapheme clusters or 4,096 original UTF-8 bytes are rejected.

There is no automatic typo correction in singular lookup methods. Even a high-ranked fuzzy result must be checked by the application before making an authoritative location selection.

Read [the full Unicode and fuzzy specification](../releases/v2.0.1-unicode-fuzzy-qualification.md), including security and performance characteristics.

## Ambiguity and errors

Global singular name methods throw
`Uganda\Exceptions\AmbiguousLocationException` if two distinct
records share a name. Use ID lookup or explicitly scoped multi-search
instead. An unknown ID returns null. Malformed or missing data raises a
loader exception rather than pretending the dataset contains zero records.

## Canonical snapshots and geospatial tools

The runtime's bundled legacy hierarchy and a
schema-versioned canonical snapshot are distinct inputs.
Use `tools/data/validate-canonical.php` to validate a snapshot and
`tools/geo/match-parishes.php` to review polygon-to-unit correspondence.

Local reviewed GeoJSON output:

    php tools/geo/build-geojson.php input.geojson canonical.json decisions.json output.geojson FID source-id

Matching proves canonical identities and parent correspondence only.
It does not establish national coverage, legal boundary status, polygon
topology or permission to redistribute a third-party source.

## Laravel consumers

The PHP core has no Illuminate dependency and does not register a service
provider. Instantiate `Uganda\Uganda` through Composer autoloading or
bind it explicitly to your own container. No undisclosed provider
auto-discovery is expected.

## Verification

`examples/standalone-v2.php` is exercised in PHPUnit. The
consumer-installation checks separately validate plain PHP and Laravel
12 projects. Complete release verification must install a published tag.
