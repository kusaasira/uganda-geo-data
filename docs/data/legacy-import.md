# Deterministic legacy dataset import

The importer converts the five existing hierarchy JSON arrays into the canonical snapshot envelope without modifying the originals or overwriting records sharing names.

## Usage

```bash
php tools/data/import-legacy.php src/Uganda/Data build/legacy-canonical.json
```

Create the `build` directory before running. The command refuses to overwrite an existing output, writes to a temporary file and atomically renames it only on success. No network access or database dependency is required.

The output uses a stable `legacy:<level>:<source-id>` identifier with the original source name preserved verbatim. These IDs are **not government-assigned codes**. Unverified source dates remain null. No correction, automatic fuzzy matching or normalization that could discard a row is performed.

## Source-to-canonical mapping

| File | Level | Parent reference |
| --- | --- | --- |
| districts.json | district | none |
| counties.json | county | district |
| sub_counties.json | subcounty | county |
| parishes.json | parish | subcounty |
| villages.json | village | parish |

Every row receives an individual canonical record. The importer rejects malformed JSON, unreadable files, missing mandatory properties and repeated IDs **within a level**. It deliberately leaves parent existence, cycles and type-specific hierarchy validation to a separate integrity validator: output creation alone does not establish that a snapshot is valid.

## Reproducibility and review

Use the original immutable input files, a pinned source commit and a source manifest/checksum to reproduce a build. Stable ordering follows the input sequence, then level order shown above. The output contains no generated timestamps or random identifiers, so a repeated import produces byte-identical JSON. Compare record totals from each raw file with the exported unit count. Any rejected row fails the whole import.

The existing `Uganda\\Uganda` constructor still reads the original JSON files. Consumers will not see this snapshot until a separate, migration-tested API change is released.

Never infer a newer administrative boundary from an old source date, and do not attach unverified polygon geometry to these records.
