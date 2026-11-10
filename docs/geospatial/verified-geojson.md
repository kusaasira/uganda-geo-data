# Building reviewed GeoJSON

The package can produce a deterministic GeoJSON FeatureCollection from public endpoint geometries, a canonical snapshot and independently reviewed parish-to-unit decisions. The exporter verifies decisions through the canonical boundary matcher before emitting any features.

```bash
php tools/geo/build-geojson.php features.geojson canonical.json reviewed-decisions.json output.geojson FID source-id
```

The command includes *only* features with reviewed canonical IDs. Unmatched or ambiguous features are excluded from this verified export and must remain visible in the separate parish coverage report. It retains the original geometry without inventing boundaries and includes the source object ID, parent and evidence date. Reviewer identities and evidence URLs remain in the internal review inputs, avoiding accidental disclosure of names or sensitive URL tokens in a public asset.

The output records `publication_rights: review_required`, `topology_status: not_checked`, `input_mapping_status` and `verification_scope: canonical_identity_and_parent_only`. `input_mapping_status: complete` only means every input feature and listed canonical unit were mapped. It never means comprehensive national coverage. Identity matching never substitutes for full geometry topology validation. A local export does **not** grant rights to republish third-party polygons in the package or attached release assets. Obtain the actual source terms and verify attribution before distribution.

This produces GeoJSON only. Shapefile and GeoPackage writers remain separate work and must pass format roundtrip testing against the verified set.
