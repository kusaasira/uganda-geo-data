# Canonical administrative identity contract

## Scope

This contract is additive and does not replace the existing `Uganda\\Uganda` object tree. It defines an interchange representation for dated, independently verifiable administrative-unit snapshots. Schema files use JSON Schema Draft 2020-12; the current package has **no runtime dependency on a JSON Schema library**.

## Identity is not a place name

- `id`: stable, opaque identifier within the canonical dataset; independent of display name, case and location hierarchy.
- `level`: explicitly typed unit, including urban variants such as city, division, ward and cell. Administrative classification is source- and date-dependent.
- `parent_id`: canonical identifier of the immediate parent or `null` for a root unit.
- `source_id` and `source_record_id`: trace the row back to a known registry source without conflating source identifiers with canonical identity.
- `external_codes`: only verified external code systems. Unknown identifiers remain absent rather than guessed.
- `aliases`: search terms, not alternate primary keys.
- `lifecycle_status`: active, retired or disputed. Effective dates are nullable until evidence is available.

For the legacy JSON importer, temporary identifiers follow `legacy:<level>:<source-record-id>`. The `legacy:` prefix makes their origin explicit. These **are not UBOS codes** and should be crosswalked to canonical IDs only after verification.

## Snapshot invariants

1. All `units` records are preserved, including different IDs sharing a normalized name and parent.
2. Each `id` is unique in its dataset snapshot. Duplicate source IDs are conflicts, not records to drop.
3. Every non-null parent must resolve in the snapshot, must not point to the unit itself and must form an acyclic relationship.
4. Parent-child type transitions must be checked against source-specific urban and rural classification rules, not hard-coded to one five-level chain.
5. A name-only search can return multiple matches. A single arbitrary first match is not a valid identity contract.
6. `source_ids` document which original sources contributed to the snapshot. Source reference dates remain explicit and may be null.
7. Geometry is represented separately by versioned Boundary records. No polygon is fabricated for unmapped units.

## Acquisition audit

`source-acquisition.schema.json` defines an immutable retrieval record including a SHA-256 digest, byte count, URI, observed UTC timestamp and content type. Retrieval metadata must be collected during the actual download. A dataset transformation must link to its original acquisition record.

## Migration behavior

These contracts initially coexist with the `districts.json`, `counties.json`, `sub_counties.json`, `parishes.json` and `villages.json` source arrays. Actual import, crosswalk generation and PHP API changes will be separately tested so existing code does not silently change shape.

The fixture in `tests/fixtures/canonical-snapshot.json` contains **fictional test locations** only. They must not be used as actual Ugandan administrative records.
