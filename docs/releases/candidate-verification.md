# Immutable v2 release-candidate verification

Before publishing alpha, beta, release candidate or stable tags, build a
candidate from a known integration commit with no publishing side effects.

    mkdir -p build/candidate
    php tools/release/build-notes.php resources/releases/v2-notes.json v2.0.0-rc.1 build/candidate/RELEASE_NOTES.md

The GitHub Actions "Verify v2 release candidate" workflow performs the full
offline sequence, including source-record conservation, notes generation,
checksum inventory generation and independent checksum verification.

The workflow has **contents: read** permissions and never tags, publishes a
release or uploads material outside CI artifacts. The notes are a draft
describing implementation, not evidence that source rights or full spatial
coverage have been verified. Preview the notes for claim accuracy and
missing features before approving release publication.

Real prerelease and stable tags require a separate explicit maintainer
decision plus a subsequent fresh installation from Packagist. Never
move or overwrite the historical v1.0.0 release tag.
