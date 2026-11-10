# Canonical and geospatial release QA evidence

Technical readiness requires distinct reports for source-record conservation,
polygon topology and reviewed feature-to-parish matching. The aggregator
preserves the distinction instead of conflating independent checks.

    php tools/release/qa-evidence.php build/release/qa.json topology.json coverage.json

The CLI emits deterministic JSON and exits nonzero on failed canonical
integrity or known topology defects. Missing or invalid reports fail
explicitly. Geometry evidence is optional for an early build but remains
unverified until supplied.

A "complete" parish match refers to the supplied source layer and canonical
snapshot only. No local test proves national completeness, legally
authoritative boundaries or redistribution rights. Accordingly this
technical report never sets release_ready to true. Those final approval
criteria require separate source evidence and maintainer review.
