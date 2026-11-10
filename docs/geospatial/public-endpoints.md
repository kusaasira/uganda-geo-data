# Public Uganda administrative boundary endpoints

This catalogue documents **candidate** boundary services. The fact that an ArcGIS FeatureServer permits anonymous queries is sufficient to begin careful technical acquisition. It is not evidence that the layer is produced by UBOS or that its geometry may be republished in a public software release.

## Current parish layer

- Layer: `Uganda_2024_Parishes`
- Service: https://services1.arcgis.com/49FlB10KwAxJa07X/ArcGIS/rest/services/Map_2_June_WFL1/FeatureServer/1
- Type: polygon; native published spatial reference 102100/3857 (Web Mercator).
- Query formats advertised: Esri JSON and GeoJSON.
- Object ID: `FID`; reported maximum record count: 2,000.
- Administrative keys: `dcode2024`, `ccode2024`, `sccode2024`, `pcode2024` plus matching name fields.
- Provenance risk: publisher identity, reference methodology, source collection date and redistribution license remain unverified. Field names alone do not establish that these are national government-issued immutable identifiers.

The 2024 UBOS census is an independently published statistical benchmark, not automatically the custodian of every GIS service tagged `2024`. Do not merge these sources solely by a same-name match.

## Technical acquisition procedure

1. Inspect service metadata from `<layer>?f=pjson`. Record service URL, layer ID, object ID field, spatial reference, supported formats and maximum record count.
2. Query `<layer>/query?where=1%3D1&returnCountOnly=true&f=json` to observe feature count.
3. Query `returnIdsOnly=true` to obtain the full object ID set; sort numerically and split into bounded batches. Use `objectIds=...` instead of relying on resultOffset, which services may implement differently.
4. Request each batch with `outFields=*`, `returnGeometry=true`, `outSR=4326` and `f=geojson`. Respect the advertised maximum count, HTTP throttling and request timeouts.
5. Save each **original** response unchanged with its source URL, request parameters, UTC retrieval time, SHA-256 checksum, byte count and HTTP headers. Never silently drop a page.
6. Check for duplicate or missing object IDs before assembling a dataset. Treat exceeding transfer limits and incomplete results as errors.
7. Reconcile polygons by verified administrative keys and parent chain. Keep unmatched or ambiguous boundaries separate for manual assessment. Validate projected coordinates, polygon rings, topology and coverage before publication.

Use `php tools/geo/inspect-arcgis.php arcgis-parishes-2024` to probe metadata locally. This command does **not** download bulk geometry. Automated collection and reusable page import are separate changes.

## Distribution

Source-specific permissions and attribution must be recorded before including third-party polygon bytes in a GitHub release or package. Where that cannot be substantiated, release the open ingestion tools and catalogue while excluding the unverified geometry. Do not call a missing parish polygon accurate or available.
