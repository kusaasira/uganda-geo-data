# Crosswalking legacy administrative identifiers

The bundled numeric IDs are historical Uganda Geo identifiers and **not verified UBOS identifiers**. The source-aware importer gives every row a unique legacy reference such as `legacy:parish:4`.

Run `php tools/data/build-id-crosswalk.php > crosswalk.json` to create a lossless identity inventory. Each legacy row receives exactly one record with its parent, source, name and an initially **null** canonical ID. Duplicate display names do not merge.

After independently validating authoritative IDs and parent chains, pass reviewed decisions as a JSON array using `php tools/data/build-id-crosswalk.php src/Uganda/Data path/to/decisions.json`:

```json
[
  {
    "legacy_id": "legacy:parish:4",
    "canonical_id": "canonical:parish:confirmed-value",
    "evidence_url": "https://www.ubos.org/document",
    "evidence_date": "2024-12-31",
    "reviewer": "assigned-data-steward"
  }
]
```

This example is **fictional** and must never be interpreted as a real mapping. The generator rejects duplicate legacy decisions, unknown IDs and implicit many-to-one assignments. The submitted evidence is a *claim supplied by the reviewer*, not independent authentication of government records. External verification and a second review remain necessary before an actual release.

No original records are rewritten. Splits, merges and boundary changes require a separate versioned event model rather than name-only assignment.
