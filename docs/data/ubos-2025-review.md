# UBOS Baseline Education Census 2025: geography source review

The Uganda Bureau of Statistics published its **Baseline Education Census 2025** report on **21 September 2026**. The official [BEC portal](https://statistics.ubos.org/bec/) provides parish-level drilldowns and an education coverage dashboard.

The [coverage dashboard](https://statistics.ubos.org/bec/dashboard.php) reports:

| Geography denominator | 2025 BEC portal | 2024 Annual Statistical Abstract | Arithmetic difference |
|---|---:|---:|---:|
| Parish geography used for primary school coverage | 10,867 | 10,717 | +150 |
| Subcounty geography used for secondary school coverage | 2,209 | 2,197 | +12 |

The 2025 portal describes **9,488 of 10,867 parishes** as containing at least one primary school and **1,698 of 2,209 subcounties** as containing a secondary school. These are *statistical coverage denominators*, not a published list of officially created administrative units. The 2024 abstract categories may not have exactly the same scope. Neither report independently proves the legal effective dates or identities behind any numerical differences.

Run:

```bash
php tools/data/review-ubos-2025.php
```

The comparator emits both category names, source-specific counts and arithmetic differences with `scope_verified=false` and `administrative_change_verified=false`. Exact unit-level reconciliation, official IDs and historical changes remain pending until authoritative record-level data is available. No dataset rows or geometry are modified.

Sources: [UBOS BEC report announcement](https://www.ubos.org/2026/09/21/baseline-education-census-bec-report-2025/), [UBOS BEC dashboard](https://statistics.ubos.org/bec/dashboard.php) and [2024 Statistical Abstract](https://www.ubos.org/wp-content/uploads/publications/The-Annual-Statistical-Abstract-2024.pdf).
