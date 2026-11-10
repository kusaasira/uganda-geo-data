# Canonical identity crosswalks and historical administrative change

The bundled numeric IDs are **legacy package identifiers**. They must not be presented as official UBOS codes without dated evidence and authority-specific identifier reconciliation.

`CanonicalIdentityCrosswalk::validate($snapshot, $links)` classifies every source record as verified, explicitly unresolved or not yet mapped. Verified one-to-one links require a canonical target, dated evidence and an HTTPS source URL. The validator rejects duplicate source IDs, undocumented assignments and multiple source records claiming one canonical identity.

`AdministrativeHistoryValidator::validate($events)` independently checks dated change events. Supported kinds include rename, reparent, split, merge, retirement and creation. Cardinality constraints prevent a split from being encoded as a simple rename. Event validation does not itself prove an official legal change or modify historical datasets.

All examples in the tests use fictional identifiers. Production mappings require official records, full parent-aware matching, reviewer verification and source provenance.

**Never silently collapse, delete or replace records simply because their names match.** Unknown relationships remain explicit pending investigation.
