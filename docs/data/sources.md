# Geographic source inventory

Uganda Geo distinguishes **source acquisition**, **administrative authority** and **permission to publish derived data**. These are separate questions. The machine-readable source registry is `resources/sources.json`.

## Formal acknowledgement of the Uganda Bureau of Statistics

**The Uganda Bureau of Statistics (UBOS)** is the official publisher of the administrative geography counts in the [National Population and Housing Census 2024](https://statistics.ubos.org/nphc/report), the [Annual Statistical Abstract 2024](https://www.ubos.org/wp-content/uploads/publications/The-Annual-Statistical-Abstract-2024.pdf) and the [2025 Statistical Abstract](https://www.ubos.org/2026/10/27/2025-statistical-abstract/).

Uganda Geo acknowledges and credits UBOS for these **UBOS-authored statistical publications**. At the 2024 census reference, the published national geography includes **135 districts, 11 cities, 312 counties, 2,191 subcounty-level units and 10,717 parishes/wards**. Those figures are attributable to the 2024 census and should not be presented as a certified count of this package's legacy JSON records or all current administrative boundaries.

Credit accompanies each documented use. The software package itself is an independent open-source project and is **not an official UBOS product or endorsement**. We do not attribute an ArcGIS layer to UBOS merely because the feature fields refer to 2024 or use administrative codes.

## Existing package data

The bundled `src/Uganda/Data/*.json` tables form a legacy reference snapshot. Their original capture date, upstream reuse conditions and external identifier system have not been independently established. Preserve the original rows and IDs during migration. Do not describe those numeric IDs as government-issued codes without evidence.

## Official reference publications

- **Uganda Bureau of Statistics (UBOS), 2024 NPHC:** https://statistics.ubos.org/nphc/report. Use a dated reference and record classification conventions.
- **UBOS, 2025 Statistical Abstract:** https://www.ubos.org/2026/10/27/2025-statistical-abstract/. Published in October 2026 and containing different indicator-specific reference periods. It does not automatically replace official unit-level administrative records.
- **UBOS, 2024 Annual Statistical Abstract:** https://www.ubos.org/wp-content/uploads/publications/The-Annual-Statistical-Abstract-2024.pdf. The abstract lists 146 districts, 11 cities, 309 counties, 2,197 subcounties, 10,717 parishes and 71,227 villages. Its district/city interpretation must not be silently equated with separate NPHC classifications.
- **ArcGIS Uganda_2024_Parishes:** https://services1.arcgis.com/49FlB10KwAxJa07X/ArcGIS/rest/services/Map_2_June_WFL1/FeatureServer/1. Public parish polygon feature service, not established as an official UBOS release. Its source ownership, collection history and reuse rights require separate evidence.

## Rules for new sources

1. Assign a stable catalogue ID, the exact source URL, the actual publisher and a reference label.
2. Preserve unknown dates as `null`; never manufacture collection or effective dates. Known reference dates use strict `YYYY-MM-DD` and retrieval timestamps use UTC `YYYY-MM-DDTHH:MM:SSZ`.
3. Keep `redistribution_status` at `unverified` until source-specific terms are documented. Marking a source `permitted` requires an HTTPS `license_url` and a nonempty `attribution` statement.
4. A publicly accessible endpoint can be used for technical discovery and reproducible acquisition, subject to its published access rules. Public accessibility alone is not a license to redistribute bundled polygons.
5. Record the retrieval timestamp, SHA-256 digest, content type, byte count and original source headers **when downloading**, not while merely cataloguing the endpoint.
6. Keep raw inputs immutable. Document schema transformations, all unmatched records and the authority of external identifiers.
7. Source metadata must travel with dataset snapshots; attribution and license evidence are release artifacts.

Run `php tools/validate-source-registry.php` to check the catalogue independently of Composer. This inventory intentionally does not change runtime lookups or replace any existing dataset.
