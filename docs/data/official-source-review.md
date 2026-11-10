# Official administrative-source currency assessment

Use `php tools/data/review-official-sources.php` to generate the dated source assessment from `resources/data/official-source-freshness.json`.

The **2024 NPHC** is the authoritative source for *its own 2024 census geography*. The **2024 Annual Statistical Abstract** uses distinct administrative classifications, so its totals are not interchangeable with the census. The **Baseline Education Census 2025** was published on **21 September 2026** and reports educational indicators by administrative units, including parishes. Its reference year is **2025**, not its publication year. The **UBOS profile** presents public counts without a clearly established geographic reference date.

None of these aggregate publications is automatically a verified legal unit register. The official source catalogue records `unit_level_identity_verified=false` until official unit-level identifiers, parent chains, reference dates and evidence for effective administrative changes are reviewed. No new village/parish codes or current boundary claims are invented.

This is an additive source-currency assessment: no legacy dataset rows, service responses or public PHP interfaces are modified.
