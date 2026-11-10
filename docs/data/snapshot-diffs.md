# Reviewable canonical dataset change reports

Each administrative snapshot is immutable and identified by its declared `snapshot_id`. The canonical unit `id` is the only valid basis for record-level comparison; matching by name would confuse distinct locations.

Compare two already validated snapshots:

```bash
php tools/data/diff-canonical.php before.json after.json
```

The command emits deterministic JSON with the input SHA-256 hashes, source record totals and separate change categories:

- **Added:** IDs in the new snapshot that were absent previously.
- **Absent:** IDs missing from the new snapshot. These are **not automatically legally retired**.
- **Renamed:** same ID, changed display name.
- **Reparented:** same ID, changed immediate parent identifier.
- **Lifecycle changed:** explicit status transitions, not inferred from absence.
- **Provenance changed:** change in the recorded source identifier.

The command validates both inputs before comparison. It does not automatically reconcile ID changes, verify legal effective dates, assert UBOS official identifiers or compare polygon geometry. Cross-source identity resolution and historical split/merge events must be reviewed separately.

A report containing any differences is labeled `review_required`. Copy the machine-readable output into a change proposal together with independent evidence, classification rationale and reviewer approval before publishing a new dated dataset snapshot.
