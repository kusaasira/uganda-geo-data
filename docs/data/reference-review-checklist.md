# Reviewing administrative reference totals

A statistical comparison is an auditable **research observation**, not a legal change record.

For every source publication:

1. Record the publisher, full publication URL, reference year and the exact administrative category label.
2. Preserve distinct urban categories, including cities, divisions, wards and cells, instead of silently folding them into district, subcounty, parish or village totals.
3. Obtain actual unit-level identifiers and parent relationships before asserting missing places or verified additions. An arithmetic count difference alone is insufficient.
4. Keep original legacy rows untouched and use dated source evidence for proposed corrections.
5. Report every unresolved name collision, source identity mismatch and classification ambiguity for review.
6. Do not claim legacy numeric IDs are official codes without an independently verified identifier crosswalk.

To reproduce the 2024 baseline:

```bash
php tools/data/reconcile-ubos.php
```

The CLI compares two dated UBOS publications with the preserved legacy input and emits explicit `classification_verified=false` and `record_level_match_verified=false` flags. They must remain false until the underlying classification and record-level evidence is established.

Official references:
- [UBOS 2024 Census reports](https://statistics.ubos.org/nphc/report)
- [UBOS 2024 Annual Statistical Abstract](https://www.ubos.org/wp-content/uploads/publications/The-Annual-Statistical-Abstract-2024.pdf)
