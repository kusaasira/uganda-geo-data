# v2 release readiness acceptance

The last integration PR is **not** permission to tag v2.0.0. Prepare an
attestation bound to the exact next/2.x or main commit, with independently
reviewed HTTPS evidence for each mandatory gate.

    php tools/release/assess-readiness.php resources/releases/readiness-template.json
    php tools/release/assess-readiness.php approved-evidence.json --enforce

The default template intentionally has no accepted gates and must report
blocked. The optional --enforce flag returns nonzero when required evidence
is missing, malformed or not tied to the current Git HEAD.

Even a fully populated evidence file reports
evidence_complete_pending_human_verification and release_ready=false.
It is a review inventory, not a publishing authorization. The maintainer
must independently inspect the linked CI runs, data provenance, geometry
topology, format round trips, source rights, package installation, release
notes and approvals.

The stable public Packagist install smoke requires a published release tag.
Pre-tag readiness and post-tag verification are therefore distinct gates:
a post-tag installation must be verified before announcing the stable
release. Never claim success ahead of publication.

This tool cannot merge branches, publish tags, change packages or upload
geometry assets. Separate explicit release approval is required.
