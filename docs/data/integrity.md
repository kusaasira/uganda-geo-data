# Canonical snapshot integrity gates

A successful import does not prove the data is trustworthy. Before using a canonical snapshot, verify conservation, identity uniqueness, explicit source references, parent resolution and the absence of cycles. This validator does **not** replace authoritative administrative reconciliation or spatial topology checks.

## Offline commands

```bash
mkdir -p build
php tools/data/import-legacy.php src/Uganda/Data build/legacy-canonical.json
php tools/data/validate-canonical.php build/legacy-canonical.json src/Uganda/Data
```

The optional second argument compares every output level against independently decoded original source arrays. Its absence still runs identity and structural checks, but cannot prove source conservation.

## Machine-readable report

The validator returns `valid`, `total_units`, `counts_by_level` and `errors`. Each error is actionable and produces exit code 1 from the CLI. Missing or corrupt inputs also fail explicitly. No row is removed, silently corrected or de-duplicated.

## Explicitly separate concerns

- **Identity:** all canonical IDs must be unique. Name collisions are not identity collisions.
- **Parent links:** every non-null parent must resolve and a chain cannot cycle.
- **Provenance:** each unit must reference a source listed in the snapshot.
- **Conservation:** each level must contain the number of rows independently read from source input when both files are supplied.
- **Authority:** legal status, date of creation, official P-codes or government code schemes require corroborating sources.
- **Geometry:** source feature matching, CRS, topology and coverage are distinct geospatial release gates.

The baseline importer preserves legacy IDs under a namespaced identifier so this validator does not imply that they are canonical UBOS codes. The current production `Uganda\\Uganda` tree is unaffected by this separate validation utility.
