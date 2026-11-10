# Release and Support Policy

## Versioning

The PHP package follows semantic versioning. A major release can change public method contracts, hierarchy key semantics or supported PHP versions. A minor release adds compatible behavior. A patch release corrects defects without intended public API breaks.

Dataset snapshots are versioned separately. An updated census reference or renamed administrative unit is not automatically equivalent to a PHP package release. Every published snapshot must declare its sources, reference dates, checksums and reconciliation record.

## Release process

1. Complete tests and independent reviews on the integration branch.
2. Build release artifacts from the exact commit intended for publication.
3. Verify package installation and documentation against the release candidate.
4. Audit source attribution, archive checksums and known limitations.
5. Publish a new immutable Git tag and release notes only after approval. Never move an existing release tag.

## Maintenance

Supported PHP versions and release branches are documented with each stable release. Old major versions may require consumers to upgrade. No fixed security response time or support duration is promised without a published maintainer commitment.

## Distribution

Public GIS endpoint accessibility permits evaluation and provenance-backed acquisition where permitted by its terms. It does not alone establish rights to redistribute its geometry as part of the package. Bundled datasets and downloadable boundary archives are subject to their own source conditions.
