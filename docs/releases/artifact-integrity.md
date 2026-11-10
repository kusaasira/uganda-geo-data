# Release artifact integrity

This release tooling targets **PHP 8.2 or later**. It depends on the package metadata correction and PHP support contract in PR #38. Do not merge into an integration branch that still declares PHP 7.4 as supported because the checksum implementation uses functions introduced after PHP 7.4.

Release assets are built outside the source tree, then bound to immutable SHA-256 checksums. The manifest contains artifact-relative paths, byte counts and cryptographic hashes without embedding build timestamps, so reruns over identical files are deterministic.

```bash
php tools/release/create-manifest.php build/release build/release-manifest.json dataset.json polygons.geojson
php tools/release/verify-manifest.php build/release build/release-manifest.json dataset.json polygons.geojson
```

Both commands require a nonempty explicit artifact inventory. The verification command compares the required file list with the manifest as well as checking every hash, so a missing expected dataset or boundary file cannot silently pass. The creation command refuses to overwrite an existing manifest.

A manifest proves which bytes were checked. It does not establish that the data is correct, authoritative, licensed for distribution or compatible with consumers. The release captain must also verify package installation, data reconciliation, geometry topology, source terms and user-visible migration documentation before tagging a stable version.

The tool rejects traversal paths, duplicate entries, symlinks that resolve outside the release root and missing or unreadable files. Do not reuse a manifest after changing an artifact.
