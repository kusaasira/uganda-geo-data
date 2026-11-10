# Uganda Geo

**Identity-safe administrative geography for Uganda, built for modern PHP.**

[![CI](https://github.com/kusaasira/uganda-geo-data/actions/workflows/ci.yml/badge.svg)](https://github.com/kusaasira/uganda-geo-data/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/kusaasira/uganda-geo?label=stable)](https://packagist.org/packages/kusaasira/uganda-geo)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Uganda Geo is an **open-source, framework-independent PHP library** for navigating districts, counties, subcounties, parishes and villages. Version **2.0.0** introduces ID-keyed collections, explicit ambiguity handling, deterministic source validation and separate tools for administrative-data review and geospatial processing.

> **Data scope:** The default PHP API uses a historical bundled administrative snapshot. Its capture date and current completeness have not been independently established. Official statistics from the **Uganda Bureau of Statistics (UBOS)** are credited as comparison and reconciliation sources, not automatically substituted into the runtime dataset. See [Data sources and attribution](#data-sources-and-attribution).

## At a glance

| | Uganda Geo 2.0 |
| --- | --- |
| Package | [kusaasira/uganda-geo](https://packagist.org/packages/kusaasira/uganda-geo) |
| Language | PHP 8.2+ with JSON extension |
| Dependencies | No mandatory framework or spatial extension |
| API | Typed administrative models and ID-based lookups |
| Coverage | District → county → subcounty → parish → village, as represented in the bundled snapshot |
| Data integrity | Preserves duplicate names by distinct source IDs |
| Integrations | Plain PHP and optional Laravel container use |
| GIS tools | Optional public endpoint acquisition, reviewed polygon matching, topology checks and local GeoJSON/Shapefile/GeoPackage export |
| Versioning | SemVer for code, separately identified source snapshots for data |
| License | [MIT](LICENSE) for the package code; third-party dataset rights may differ |

## Why 2.0?

Administrative place names are **not unique identifiers**. Two different parishes may share the same name, even within a larger area. A name-keyed collection can hide legitimate records if one name overwrites another.

Version 2 changes that contract:

- **Preserves identity:** Districts and their descendants are indexed by source IDs, not normalized names.
- **Handles ambiguity:** Global singular name lookups reject multiple matches. Parent-scoped searches let callers choose the intended locality.
- **Navigates ancestry:** Find a unit, retrieve its direct children or resolve its complete chain of administrative parents.
- **Fails loudly:** Invalid or missing packaged data raises an exception rather than pretending the dataset is empty.
- **Audits changes:** Optional import and reconciliation tools preserve source provenance, detect broken parent relationships and produce reproducible data manifests.
- **Keeps GIS optional:** No GDAL, Laravel or geospatial extension is required just to use administrative lookups.

## Installation

Install the **stable 2.x** package from Packagist:

```bash
composer require "kusaasira/uganda-geo:^2.0"
```

Requirements: PHP **8.2 or newer**, Composer **2** and PHP's JSON extension. Release-specific install availability can be checked on [Packagist](https://packagist.org/packages/kusaasira/uganda-geo).

Version 2 is a **breaking upgrade** from v1. Read the [migration guide](docs/migration/v1-to-v2.md) before upgrading an existing integration.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Uganda\Uganda;

$geo = new Uganda();

// Obtain a typed district model.
$district = $geo->district('Mukono');

echo $district->id() . ': ' . $district->name() . PHP_EOL;

// Look up that exact district by source identity.
$exact = $geo->findById('district', $district->id());

// Navigate direct child units.
$counties = $geo->childrenOf('county', $district->id());

// A name can match several places. Do not discard candidates.
$matches = $geo->searchByName('parish', 'Central');

foreach ($matches as $parish) {
    echo $parish->id() . ' - ' . $parish->name() . PHP_EOL;
}
```

Or execute the versioned example in a local repository checkout:

```bash
composer install
php examples/standalone-v2.php
```

### Resolve an ambiguous place safely

```php
<?php

use Uganda\Uganda;
use Uganda\Exceptions\AmbiguousLocationException;

$geo = new Uganda();

try {
    $parish = $geo->parish('Central');
} catch (AmbiguousLocationException $exception) {
    // Ask the caller to select the intended parent or a source ID.
    $candidates = $geo->searchByName('parish', 'Central');
}

// Select a real parent ID before a scoped lookup.
$district = $geo->district('Mukono');
foreach ($district->counties() as $county) {
    foreach ($county->subCounties() as $subcounty) {
        $parishes = $geo->searchByName('parish', 'Central', $subcounty->id());
        // Handle every matching parish in this particular subcounty.
    }
}
```

An ID is meaningful **within its administrative level and source snapshot**. Numeric package source IDs are **not** guaranteed to be official UBOS administrative codes. Use a verified crosswalk when matching IDs from external systems.

### Navigate parents and children

```php
<?php

use Uganda\Uganda;

$geo = new Uganda();

$district = $geo->district('Mukono');
$county = reset($district->counties());

if ($county instanceof \Uganda\County) {
    $subcounties = $geo->childrenOf('subcounty', $county->id());
    foreach ($subcounties as $subcounty) {
        $ancestors = $geo->ancestorsOf('subcounty', $subcounty->id());
        echo $subcounty->name() . ' has ' . count($ancestors) . " ancestors\n";
    }
}
```

For examples that must work regardless of a particular source name, use [examples/standalone-v2.php](examples/standalone-v2.php).

## API reference

| Method | Behavior |
| --- | --- |
| `districts()`, `counties()`, `subCounties()`, `parishes()`, `villages()` | Collect each level from the bundled hierarchy |
| `district($name)`, `county($name)`, `subCounty($name)`, `parish($name)`, `village($name)` | Return a unique named unit or throw |
| `findById($level, $id)` | Find an exact level-specific integer ID or return `null` |
| `searchByName($level, $name, $parentId = null)` | Return every exact normalized-name match, optionally scoped to a parent |
| `childrenOf($childLevel, $parentId)` | Return direct children of a known parent |
| `ancestorsOf($level, $id)` | Return ancestors from root to immediate parent |

Supported levels: `district`, `county`, `subcounty`, `parish` and `village`. A name is a display property, **never a globally unique lookup key**.

See the [complete v2 API reference](docs/api/v2-reference.md) for return contracts, errors and more examples.

## Laravel integration

The production library has **no Illuminate dependency** and does not register a service provider. Use it directly through Composer autoloading:

```php
use Uganda\Uganda;

$geo = app(Uganda::class);
$districts = $geo->districts();
```

Laravel container integration is covered by independent consumer smoke tests. No service-provider configuration or auto-discovery is required.

## Data sources and attribution

**UBOS is the official publisher of the 2024 census and statistical references** used to evaluate administrative-geography currency. We acknowledge and credit the [Uganda Bureau of Statistics](https://www.ubos.org/) for those publications.

| Source | Role | What it establishes |
| --- | --- | --- |
| [Uganda passport portal](https://passports.go.ug/) | Reported original source of the legacy bundled package data | Historical package locality rows; capture date not verified |
| [UBOS NPHC 2024 reports](https://statistics.ubos.org/nphc/report) | Official census geography and statistical reference | 2024 census classifications and dated published counts |
| [UBOS 2024 Statistical Abstract](https://www.ubos.org/wp-content/uploads/publications/The-Annual-Statistical-Abstract-2024.pdf) | Official published administrative statistics | Its own reference-date and classification context |
| [UBOS 2025 Statistical Abstract](https://www.ubos.org/2026/10/27/2025-statistical-abstract/) | Updated official reference publication | Newer reported indicators, subject to each indicator's reference period |
| [Public ArcGIS parish feature layer](https://services1.arcgis.com/49FlB10KwAxJa07X/ArcGIS/rest/services/Map_2_June_WFL1/FeatureServer/1) | Candidate optional polygon source | Public GIS service; UBOS ownership and redistribution permission **not established** |

The NPHC 2024 reports identify **135 districts and 11 cities**, **312 counties**, **2,191 subcounty/division/town-council units** and **10,717 parishes/wards** for the census reference. These are **official census figures**, not counts certified for the package's historical JSON snapshot and not automatically equivalent to current legal administrative units.

We credit UBOS for UBOS-authored publications without misattributing the passport-derived legacy dataset or unrelated third-party ArcGIS polygons. See [source inventory](docs/data/sources.md), [official-source assessment](docs/data/official-source-review.md) and [public GIS endpoints](docs/geospatial/public-endpoints.md).

## Optional geospatial tooling

The repository includes tooling to inspect and acquire public polygon endpoints, reconcile parish identities against a canonical snapshot, validate geometry and export reviewed data to GIS formats.

The **PHP runtime does not ship a verified national parish boundary archive**. GIS tooling requires separately acquired source data and, for format conversion, **GDAL**. Example operator commands:

```bash
# Inspect the catalogued public feature service.
php tools/geo/inspect-arcgis.php arcgis-parishes-2024

# Download a provenance-aware copy to a NEW local directory.
php tools/geo/download-arcgis.php arcgis-parishes-2024 ./build/arcgis-2024

# After human-reviewed identity matching, export verified GeoJSON to a NEW directory.
php tools/geo/export-formats.php reviewed-parishes.geojson ./build/parish-formats
```

The generated local formats include **GeoJSON**, **GeoPackage** and an ESRI Shapefile set with `.shp`, `.shx`, `.dbf`, `.prj` and `.cpg`.

**Publication distinction:** Public access is enough to discover and ingest an endpoint under applicable access terms. It does not by itself grant redistribution rights. Local GIS conversion and sample topology tests do not prove nationwide completeness. Unverified third-party geometry files are **not attached** to the v2.0.0 source release.

Read [public endpoint guidance](docs/geospatial/public-endpoints.md), [coverage audit](docs/geospatial/parish-coverage-audit.md) and [geometry archive policy](docs/releases/geometry-archives.md).

## Development and verification

```bash
composer install
composer test
composer cs-test
./vendor/bin/phpstan analyse src --level 9
php examples/standalone-v2.php
```

CI covers PHP **8.2, 8.3, 8.4 and 8.5**, the lowest-supported dependency set, standalone/Laravel consumers, data validation and optional GIS workflows. Test fixtures prove code behavior, not external administrative currentness or source publication rights.

Security reporting: [SECURITY.md](SECURITY.md). Contributions and data corrections: [CONTRIBUTING.md](CONTRIBUTING.md).

## Versioning, releases and migration

- [v2.0.0 release](https://github.com/kusaasira/uganda-geo-data/releases/tag/v2.0.0): stable major release with public API breaking changes.
- [CHANGELOG.md](CHANGELOG.md): historical release notes and versioned changes.
- [Migration guide](docs/migration/v1-to-v2.md): required v1-to-v2 upgrade steps.
- [Source attribution and licenses](docs/data/sources.md): provenance and redistribution boundaries.

Package versions describe the **software release**. Administrative datasets require their own reference dates, source identities and checksums. A newer Composer tag does not mean every included geographic name is current.

## Credits and license

Created and maintained by [Joshua Kusaasira](https://github.com/kusaasira). Thanks to all [contributors](https://github.com/kusaasira/uganda-geo-data/graphs/contributors).

Administrative statistical references are credited to the **Uganda Bureau of Statistics (UBOS)**. The historical packaged locality dataset was originally attributed to Uganda's passport portal. Third-party geometry sources retain their own rights and attribution requirements.

The **package source code** is released under the [MIT License](LICENSE). This license does not automatically cover externally sourced administrative publications or GIS feature datasets.
