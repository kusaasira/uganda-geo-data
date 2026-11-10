# Migrating from Uganda Geo v1 to v2

This guide describes the **stable 2.0.0 public API** and the required migration from the previously published 1.0.0 API. Version 2 uses a different namespace and query contract.

## Why the major-version change?

The published `v1.0.0` package exposes `Uganda\Geo`. Version 2 exposes `Uganda\Uganda`, retains records by numeric source ID and rejects ambiguous single-result lookups instead of returning a potentially incorrect first match.

| Concern | Published v1 | Proposed v2 |
| --- | --- | --- |
| Root API | `Uganda\Geo` | `Uganda\Uganda` |
| Return shape | Chainable lookup methods returning JSON through `all()` | Typed location model objects and ID-keyed maps |
| Duplicate-name behavior | Singular chains may select the first match | All records preserved; ambiguities explicit |
| Source ID meaning | Legacy dataset identifiers | Legacy dataset identifiers, not verified official UBOS codes |
| Installation | Stable 1.0.0 available | Stable 2.x package via Composer, after confirming installation |

## Upgrade procedure

1. Confirm your PHP runtime is 8.2 or later and take a regression baseline of existing v1 behavior.
2. Find code that indexes district, county, parish or village maps by lowercase name.
3. Replace identity-sensitive access with `findById($level, $id)` or an explicit `searchByName($level, $name, $parentId)`.
4. Handle `AmbiguousLocationException` from singular name lookups and present candidate identity and parent chain to users.
5. Preserve existing historical package IDs only where they are confirmed. Do not assume they are official government codes.
6. Run consumer integration tests, import historical location references through an audited crosswalk and compare source-record counts.
7. Run `composer require "kusaasira/uganda-geo:^2.0"`, complete a production-like smoke test and deploy with a standard rollback strategy.

## Example

```php
use Uganda\Uganda;

$uganda = new Uganda();
$district = $uganda->district('Mukono');

$matchingDistrict = $uganda->findById('district', $district->id());
assert($matchingDistrict === $district);

// A query can legitimately return more than one record.
$matches = $uganda->searchByName('county', 'Lower Madi');
foreach ($matches as $county) {
    echo $county->id(), ': ', $county->name(), PHP_EOL;
}
```

Do not use a global name alone as a persistent identity. If a location was renamed, reassigned or retired, use the source provenance and dated crosswalk to reconcile it.
