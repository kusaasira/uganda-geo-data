# Reviewable canonical snapshot differences

`php tools/data/diff-snapshots.php before.json after.json` compares two canonical snapshots by **stable unit ID**, regardless of source row ordering.

The output differentiates records **added**, **missing from the next snapshot** and **modified**. Modified entries show before/after values for name, parent, level, lifecycle status, aliases, external codes and provenance. Results are sorted by stable ID to keep reviews reproducible.

A record missing from the next snapshot is **not automatically a retired or legally dissolved unit**. Only explicit evidence-backed lifecycle changes should be represented as retirement. Input must contain unique IDs and valid lists, otherwise comparison fails instead of silently overwriting rows.

The comparison is read-only and does not modify the source files or canonical snapshots. Unverified code/name crosswalks remain separate review work.
