# Evidence-backed parish boundary matching

Public ArcGIS FeatureServer object IDs are **source feature IDs**, not stable Ugandan administrative codes. The tool `php tools/geo/match-parishes.php features.geojson canonical-snapshot.json decisions.json FID` checks an explicitly reviewed feature-to-unit crosswalk. The source GeoJSON is validated by the existing importer before matching.

Each decision must provide `object_id`, `unit_id`, `parent_unit_id`, an HTTPS `evidence_url`, an ISO `evidence_date` and a named `reviewer`. The unit must be a parish or ward in the supplied canonical snapshot and the parent must match exactly.

The output contains every accepted mapping plus the exact unmatched source feature IDs and canonical parish/ward IDs. An empty decision list does **not** automatically match anything, even if polygon names match source locality names. Duplicate assignments, wrong parents and unsupported codes fail explicitly.

The evidence URL represents a human attestation, not automatic verification of the government issuer. This PR does not modify geometry, generate shapefiles, infer polygon boundaries or claim 100% coverage. Topology validation and distribution rights require separate release verification.
