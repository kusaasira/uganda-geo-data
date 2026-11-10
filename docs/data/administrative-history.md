# Reviewing dated administrative changes

The `LegacyIdentityCrosswalk` assigns reviewed identifiers while preserving each source row. Administrative transitions require a separate event record and must not be inferred from matching names.

`AdministrativeHistoryValidator::validate($events)` checks the shape, identity continuity, effective date and HTTPS evidence of change events. Supported events: `create`, `rename`, `reparent`, `split`, `merge` and `retire`. Renames and parent changes preserve a unit ID. A split must name at least two output identities and a merge must identify at least two inputs.

This validator checks the submitted declaration. It does not independently authenticate the legal instrument or determine whether a gazette has taken effect. Release validation requires official provenance and human review of every identity transition.

Both the crosswalk and event history are additive. Neither rewrites historical JSON source files or assigns official codes without evidence.
