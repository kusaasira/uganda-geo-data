# Uganda Geo v2.0.1: storage engine and search performance design

## Objective

Provide fast, deterministic PHP administrative lookups with minimal production dependencies, no mandatory database and preserved source-local location identities. The public v2.0.0 object model remains the compatibility contract.

## Investigated alternatives

| Strategy | Advantages | Limitations | Decision |
| --- | --- | --- | --- |
| Eager tree plus five-level indexes | Very fast warmed ID and name queries | Extra allocation and startup cost even for district-only requests | Replace global eager indexing |
| Immutable model tree with per-level on-demand indexes | No extra runtime dependency, preserves objects and direct child lists, efficient warm exact lookups | First use of a new level still needs an indexing pass | Selected |
| Streaming per-level enumeration | No additional flattened array or secondary search index | Linear traversal and not a replacement for repeated indexed lookup | Supported as optional API |
| Dense `SplFixedArray` | Compact vector for contiguous numeric offsets | Source IDs are not necessarily dense and need a separate ID map | Not selected without evidence |
| SQLite/FTS5 | Useful for much larger datasets or persistent full-text queries | Runtime extension, query setup and deployment cost compared with in-memory lookup | Not required |
| Custom binary snapshots | Potentially compact on disk | Needs a versioned, independently audited parser and integrity contract | Deferred pending measured evidence |
| OPcache preloading | Reduces PHP source compilation cost in persistent runtimes | Deployment-specific, process restart semantics and not useful for ordinary CLI execution | Optional operator tuning only |

The PHP manual confirms that `SplFixedArray` uses less memory but permits only integer indexes within its fixed size: https://www.php.net/splfixedarray

The PHP manual states that preloading is primarily relevant to persistent production processes and keeps code resident until process restart: https://www.php.net/opcache.preloading.php

The production package still requires only PHP 8.2+ and `ext-json`. No native extensions or additional libraries were imposed by these choices.

## Implemented core architecture

- Hydrate bundled source datasets bottom-up, one table at a time, releasing consumed decoded child buckets.
- Canonical model objects retain immediate child collections.
- Child queries reuse parent collections rather than allocating duplicate child adjacency maps.
- Identity indexes are created for the requested level only.
- Normalized name indexes are constructed separately from ID indexes.
- Sorted prefix name indexes are created only on the first prefix request for a level.
- Ancestry paths are memoized for repeated successful queries without caching unknown IDs.
- Generator-based iteration permits full-source enumeration without an extra flattened array.
- Source-local ID and parent identifiers remain mandatory; ambiguous name matches are never silently resolved.

## Benchmark methodology and evidence

The PR workflow `.github/workflows/core-comparison.yml` checks out the exact base and proposed commit and runs the same script in independent PHP 8.4 processes. It fails if the result's ordered model-class/ID sequence changes in six query scenarios. An additional whole-hierarchy checksum compares every returned source ID, display name and parent link in deterministic traversal order.

All cold/warm timing values are observations on GitHub-hosted runners, not application service-level objectives. Compare multiple runs on equivalent hardware and code paths. Do not advertise nanosecond guarantees or theoretical impossibility of further optimization.

## Accuracy, security and behavior

- Existing `toArray()` hierarchies remain unchanged.
- Compact `JsonSerializable` output is an additional flat representation.
- Unknown IDs return null for direct lookup while unknown parents yield empty child lists.
- Unknown hierarchy levels raise `InvalidArgumentException`.
- Corrupt source records, duplicate IDs and orphan parent relationships fail closed.
- Explicit source provenance and schema versioning distinguish legacy package IDs from independently verified government codes.

## Future research

Broader prefix workloads are measured by the differential performance workflow. Opt-in ICU Unicode folding and fuzzy matching now have their own indexed candidate search, seeded property oracles and real-dataset latency budgets. See [Unicode and fuzzy qualification](v2.0.1-unicode-fuzzy-qualification.md). Optional database and binary formats must demonstrate advantages over the selected in-memory representation before becoming part of the default runtime.
