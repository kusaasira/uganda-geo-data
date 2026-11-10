# Reviewed geometry coverage in the static explorer

Generate an explorer from a canonical snapshot and, optionally, a coverage
report produced by the reviewed parish matcher:

    php tools/data/build-explorer.php canonical.json build/explorer coverage.json

The coverage report must describe exactly the parish and ward identities in
the supplied snapshot. Verified and unmapped identity sets must partition the
snapshot's parish/ward inventory. A mismatch fails the build rather than
displaying a misleading percentage.

Only the reviewed count, denominator and unmapped count are exposed to the
browser. Private reviewer names, evidence URLs and raw source features are
never included in the generated index.

If no report is supplied, the explorer states that reviewed geometry coverage
was not provided. All coverage describes **the supplied canonical snapshot**,
not nationwide administrative completeness or boundary legality. The user
interface does not claim official accuracy or publication rights.
