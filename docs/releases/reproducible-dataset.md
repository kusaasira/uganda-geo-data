# Reproducible canonical dataset builds

Generate a deterministic, offline canonical snapshot from the bundled source:

    mkdir -p build
    php tools/release/build-dataset.php src/Uganda/Data build/canonical-release

The output directory must be absent. The command stages files in a separate
directory and atomically publishes only after validation and checksum checks.

Artifacts:

- canonical.json: every source record preserved under canonical legacy identity.
- qa.json: source file hashes, source counts and canonical integrity results.
- manifest.json: byte lengths and SHA-256 checksums for both artifacts.

The build has no timestamps or network dependencies, so identical input files
produce byte-for-byte identical outputs. Pull-request CI runs two independent
builds and compares all three files.

These snapshots are a reproducible representation of the **historical bundled
data**, not proof of the latest legal administrative register or parish
boundary correctness. External UBOS and GIS acquisitions need their own source
manifests, dated decisions and review before their data can replace a
published snapshot.
