# Coverage audit for parish and ward polygons

`ParishBoundaryMatcher` owns the canonical source-polygon-to-parish mapping rules. This complementary `ParishGeometryCoverageAuditor` reports how many of those mappings are reviewed, explicitly unresolved or still completely unmatched.

```bash
php tools/geo/audit-parish-coverage.php features.geojson canonical.json reviewed.json unresolved-ids.json FID
```

The review decisions use the **same** `object_id`, `unit_id`, `parent_unit_id`, `evidence_url`, `evidence_date` and `reviewer` fields accepted by the main matcher. `unresolved-ids.json` is an optional JSON list of source feature ID strings, kept separate from verified mappings. Each unresolved ID must refer to an existing unmatched feature and cannot also be assigned to a parish.

The output counts verified mappings, lists explicitly unresolved polygons and retains a distinct list for feature IDs with no decision. It also lists canonical parishes or wards without a verified polygon. An empty dataset can never claim 100% coverage.

**A complete result means all features and canonical units in the supplied snapshot were matched.** It does not establish that the source layer has every Uganda parish, that the shapes are topologically valid or that they are legally authoritative. Those require independent source and geometry verification before publishing any shapefile assets.
