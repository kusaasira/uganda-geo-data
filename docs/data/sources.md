# Geographic source inventory

Uganda Geo distinguishes **source acquisition**, **administrative authority** and **permission to publish derived data**. These are separate questions. The machine-readable source registry is `resources/sources.json`.

## Existing package data

The bundled `src/Uganda/Data/*.json` tables form a legacy reference snapshot. Their original capture date, upstream reuse conditions and external identifier system have not been independently established. Preserve the original rows and IDs during migration. Do not describe those numeric IDs as government-issued codes without evidence.

## Official reference publications

- **Uganda Bureau of Statistics (UBOS), 2024 NPHC:** https://statistics.ubos.org/nphc/report. Use a dated reference and record classification conventions.
- **UBOS, 2024 Annual Statistical Abstract:** https://www.ubos.org/wp-content/uploads/publications/The-Annual-Statistical-Abstract-2024.pdf. The abstract lists 146 districts, 11 cities, 309 counties, 2,197 subcounties, 10,717 parishes and 71,227 villages. Its district/city interpretation must not be silently equated with separate NPHC classifications.
- **ArcGIS Uganda_2024_Parishes:** https://services1.arcgis.com/49FlB10KwAxJa07X/ArcGIS/rest/services/Map_2_June_WFL1/FeatureServer/1. Public parish polygon feature service, not established as an official UBOS release. Its source ownership, collection history and reuse rights require separate evidence.

## Rules for new sources

1. Assign a stable catalogue ID, the exact source URL, the actual publisher and a reference label.
2. Preserve unknown dates as `null`; never manufacture collection or effective dates.
3. Keep `redistribution_status` at `unverified` until source-specific terms are documented.
4. A publicly accessible endpoint can be used for technical discovery and reproducible acquisition, subject to its published access rules. Public accessibility alone is not a license to redistribute bundled polygons.
5. Record the retrieval timestamp, SHA-256 digest, content type, byte count and original source headers **when downloading**, not while merely cataloguing the endpoint.
6. Keep raw inputs immutable. Document schema transformations, all unmatched records and the authority of external identifiers.
7. Source metadata must travel with dataset snapshots; attribution and license evidence are release artifacts.

Run `php tools/validate-source-registry.php` to check the catalogue independently of Composer. This inventory intentionally does not change runtime lookups or replace any existing dataset.
