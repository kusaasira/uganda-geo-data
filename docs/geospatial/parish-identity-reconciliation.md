# Matching parish polygons to canonical administrative identities

Public ArcGIS endpoints return source feature identifiers such as `FID`. These are **not stable Ugandan administrative identifiers**. A polygon should be linked to a canonical parish only after its identity and immediate parent subcounty are verified against dated evidence.

`ParishGeometryReconciler::reconcile($units, $features, $decisions)` reports every polygon as verified, unresolved or unmatched and every canonical parish as mapped or unmapped. Every verified decision must contain `source_object_id`, `canonical_parish_id`, `canonical_parent_id`, `status=verified`, `evidence_url` and `evidence_date`.

A feature with a matching name but unknown codes and ancestry must remain unresolved; **name-only matching is never accepted**.

```json
[
  {"source_object_id":"101","canonical_parish_id":"legacy:parish:123","canonical_parent_id":"legacy:subcounty:45","status":"verified","evidence_url":"https://example.org/official","evidence_date":"2024-05-10"},
  {"source_object_id":"102","canonical_parish_id":null,"status":"unresolved"}
]
```

This is a fictional illustration and does not establish any real Ugandan parish mapping. A one-to-many or many-to-one geographic relationship needs a separate reviewed change event instead of silently attaching multiple source features to one canonical ID.

Run the offline reconciliation:

```bash
php tools/geo/reconcile-parishes.php canonical.json source-page.geojson FID decisions.json
```

The CLI parses and validates the GeoJSON using the existing source-object-ID and geometry contracts, then emits a report with precise unmatched IDs. Incomplete coverage is explicitly labeled and does not become a false claim of complete parish mapping. This does not aggregate boundaries, correct topology or distribute geometry release assets.
