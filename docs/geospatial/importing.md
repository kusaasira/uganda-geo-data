# Reproducible parish polygon acquisition

Uganda Geo can download GeoJSON polygons from a **publicly accessible, explicitly catalogued** ArcGIS FeatureServer. It does not need to treat the service as an official UBOS source, and it does not require any credentials. The tool is opt-in and never runs during installation or application startup.

## Commands

```bash
php tools/geo/inspect-arcgis.php arcgis-parishes-2024
mkdir -p build/geometry
php tools/geo/download-arcgis.php arcgis-parishes-2024 build/geometry/arcgis-parishes-2024
```

The target directory must not exist. Acquisition first requests the complete object-ID list, then downloads at most 100 features per GeoJSON page. Using stable object-ID chunks avoids offset pagination differences and protects against the published 2,000-record-per-query limit.

## Validation and invariants

- Outbound hosts are allowlisted and HTTPS-only, redirects are disabled and response sizes/timeouts are bounded.
- Every ID from `returnIdsOnly=true` must appear in exactly one feature response; missing, extra or duplicate IDs fail the run.
- Every returned geometry must be a valid structural Polygon/MultiPolygon with closed rings and finite longitude/latitude values.
- The downloader requests `outSR=4326` and records the service's declared original CRS separately.
- Each original GeoJSON page is saved unchanged with SHA-256, byte count and the requested object IDs.
- The final manifest records retrieval time, source URL, object ID field, raw inventory hash, page digests and total feature count.
- The output directory is only made visible at its final path after complete acquisition. Failed runs may leave a `.partial-...` directory for inspection and manual cleanup; those partial directories must never be published.
- Reuse rights remain `unverified`. This tool is for authorized public endpoint acquisition and local validation, **not** automatic release-asset republication.

## Scope and known limitations

Actual network acquisition requires an environment able to access the public ArcGIS service. Unit tests use offline fixtures and therefore do not certify current external uptime, total parish coverage, geometry topology or administrative-code correspondence.

This PR does not yet produce ESRI Shapefile or GeoPackage distributions. Those exports require verified boundary matching and geometry topology checks, followed by the appropriate source reuse decision. The resulting raw page files are intentionally excluded from source control.

## Mapping to administrative units

The `FID` field is a *source feature ID*, not a stable Uganda Geo unit ID. Administrative code columns and full parent ancestry must be reconciled against canonical records in a separate step. Name-only matching is never an acceptable fallback when multiple localities share a name.
