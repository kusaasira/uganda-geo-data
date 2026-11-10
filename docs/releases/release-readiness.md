# v2 release readiness acceptance

The last integration PR is **not** permission to tag v2.0.0. Prepare an
attestation bound to the exact next/2.x or main commit, with independently
reviewed HTTPS evidence for each mandatory gate.

    php tools/release/assess-readiness.php resources/releases/readiness-template.json
    php tools/release/assess-readiness.php approved-evidence.json --require-attestations

The default template intentionally has no accepted gates and must report
blocked. The optional --require-attestations flag returns nonzero when required
evidence pointers are missing, malformed or not tied to the checked-out
repository commit. It validates the structure of attestations only and does
not confirm that the URLs actually support the claims.

Even a fully populated evidence file reports
attestations_supplied_pending_independent_review and release_ready=false.
It is a review inventory, not a publishing authorization. A green inventory
workflow merely proves the pending-state handling works. The maintainer
must independently inspect the linked CI runs, data provenance, geometry
topology, format round trips, source rights, package installation, release
notes and approvals.

Before approving a stable release, verify that a real alpha, beta or RC tag
can be installed independently through Packagist. This **prerelease** install
is the pre-stable gate and avoids the impossible requirement to install a
stable tag that has not been published yet.

After publishing the immutable stable tag, perform a separate fresh stable
Packagist installation and confirm its runtime behavior before announcing
success. The post-stable smoke is deliberately not a prerequisite for
publishing the very same tag. Never claim it passed ahead of publication.

This tool cannot merge branches, publish tags, change packages or upload
geometry assets. Separate explicit release approval is required.
