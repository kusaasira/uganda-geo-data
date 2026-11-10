# Reconciling the bundled hierarchy with official 2024 totals

Uganda Geo preserves its legacy hierarchy and compares its record counts against **two distinct UBOS publications**. Run:

```bash
php tools/data/reconcile-ubos.php
php tools/data/reconcile-ubos.php path/to/legacy-json-directory
```

The command imports source rows without name-key deduplication, then emits the source count, mapped category and arithmetic difference for each reference. **These differences are not classified as missing or duplicate administrative units.** The bundled hierarchy has an unknown collection date, historical cities/wards may be folded into other categories and the two official publications use different grouping conventions.

- The **2024 NPHC Final Report Volume 1, Table 21.1** reports 135 districts and 11 cities, 312 counties, 2,191 subcounty/division units and 10,717 parish/ward units.
- The **2024 Annual Statistical Abstract** reports 146 districts and 11 cities, 309 counties, 2,197 subcounties, 10,717 parishes and 71,227 villages. These totals must not be automatically added or substituted for the census counts.

The reference manifest is `resources/data/ubos-2024-reference.json`. It records publication-specific classifications and URLs. The report deliberately sets `classification_verified=false` and `record_level_match_verified=false`. Those flags require dated unit-level source data, exact parent-aware matching, independent identifier verification and explicit reviewer decisions.

This report does **not** alter source JSON files, infer boundary changes or assign UBOS codes to legacy IDs.

The NPHC Final Report URL points directly to the official UBOS census portal, rather than a third-party government mirror. Source hosting does not independently resolve differences in administrative scope or legal effective dates.
