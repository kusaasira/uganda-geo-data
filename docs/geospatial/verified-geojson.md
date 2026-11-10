# Building reviewed GeoJSON

The package can produce a deterministic GeoJSON FeatureCollection from public endpoint geometries and an independently reviewed parish-to-unit mapping report.

```bash
php tools/geo/build-geojson.php features.geojson reviewed.json output.geojson FID source-id
```

The command includes *only* features with reviewed canonical IDs. Unmatched or ambiguous features are excluded from this verified export and must remain visible in the separate parish coverage report. It retains the original geometry without inventing boundaries and includes the source object ID, parent and review evidence.

The output records `publication_rights: review_required`. A local export does **not** grant rights to republish third-party polygons in the package or attached release assets. Obtain the actual source terms and verify attribution before distribution.

This produces GeoJSON only. Shapefile and GeoPackage writers remain separate work and must pass format roundtrip testing against the verified set.
