# Contributing administrative data corrections

Administrative-data maintenance should be accessible to people who are not PHP developers. The issue form `Administrative data correction` is the entry point for corrections to a district, city, county, subcounty, parish, ward, village or other unit.

## Evidence required

Every correction must include the affected unit ID or an explicit `unknown` marker, the unit type, name, immediate parent ID and parent name, existing value, exact proposed value, a public source URL and a dated evidence reference. A source publication date is **not necessarily** the date a new administrative unit legally took effect. Cite the relevant page, gazette notice, section or geospatial feature ID.

For polygon changes identify the geometry service, native CRS, source feature ID, source version and exact layer. Public accessibility alone does not verify source ownership, administrative correctness or permission to redistribute geometry.

Avoid personal identification numbers, private contacts and unsupported personal details. A public administrative correction is not a venue for sensitive resident data.

## Review stages

1. **Submitted:** issue complete enough to identify the intended geographic entity; does not change data.
2. **Needs evidence:** reviewer requests a missing parent, inconsistent identity, dated source or clarification. Keep the reported claim separate from canonical values.
3. **In review:** compare against UBOS census publications, any newer official administrative authority and the pinned original snapshot. For geometry compare source codes plus parent chain, never only the name.
4. **Approved:** document the decision, reviewer, supporting sources and intended snapshot release. Preserve the original source record and produce a reviewable before/after diff.
5. **Rejected or disputed:** explain competing dated evidence and retain the issue's audit record. No automated fuzzy correction or deletion.

A second reviewer should check high-impact changes such as district restructuring, parish reassignment, source code changes or geometry edits affecting many descendants.

## Data and software versioning

A correction becomes available only in a new dated dataset snapshot after integrity checks succeed. Existing published snapshots and package versions are immutable. Link a software PR only when the change involves importer behavior, schema validation or a new API contract; routine data corrections should not require edits to PHP internals.

## Acceptance criteria

- All affected IDs exist or are explicitly unresolved.
- Parent chain and unit type are consistent with the chosen dated authority.
- No source row disappears and unaffected unit IDs remain stable.
- Ambiguous same-name entries remain distinct.
- Source URL, evidence date, method and reviewer decision are recorded.
- Dataset difference report and full integrity tests pass before publication.

The issue form and correction JSON schema define the proposal contract. Neither submits edits directly to the canonical data or downloads private attachments.
