# Building reviewed GeoJSON

The package can produce a deterministic GeoJSON FeatureCollection from public endpoint geometries, a canonical snapshot and independently reviewed parish-to-unit decisions. The exporter verifies decisions through the canonical boundary matcher before emitting any features.

```bash
php tools/geo/build-geojson.php features.geojson canonical.json reviewed-decisions.json output.geojson FID source-id
```

The command includes *only* features with reviewed canonical IDs. Unmatched or ambiguous features are excluded from this verified export and must remain visible in the separate parish coverage report. It retains the original geometry without inventing boundaries and includes the source object ID, parent and dated source evidence. Reviewer names are retained in the internal review input rather than exported to public GeoJSON.

The output records `publication_rights: review_required`, `topology_status: not_checked` and `verification_scope: canonical_identity_and_parent_only`. Identity matching never substitutes for full geometry topology validation. A local export does **not** grant rights to republish third-party polygons in the package or attached release assets. Obtain the actual source terms and verify attribution before distribution.

This produces GeoJSON only. Shapefile and GeoPackage writers remain separate work and must pass format roundtrip testing against the verified set.
