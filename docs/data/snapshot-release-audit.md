# Auditing a dataset release candidate

The canonical diff implementation lives in `CanonicalSnapshotDiffer` and is introduced separately. `SnapshotReleaseAuditor` consumes that contract to provide release-facing named categories and preserve the underlying machine-readable change list.

```bash
php tools/data/audit-snapshot-release.php before.json after.json
```

The command validates both canonical snapshots, computes SHA-256 digests of their **exact input bytes** and writes a deterministic JSON report containing additions, absences, modifications and grouped renames, reparenting, lifecycle and provenance transitions.

- `missing_from_next_snapshot` means absent from the compared input. It is **not proof of legal retirement**.
- Each difference requires dated supporting evidence and a reviewer decision before inclusion in published administrative data.
- The checksum fields bind the change report to the exact inputs rather than only an administrative-unit count.
- The two original snapshots are never edited.

This is an additive release auditor, not another definition of the canonical diff class. Use the regular diff utility for general comparisons and this command for release approval evidence.
