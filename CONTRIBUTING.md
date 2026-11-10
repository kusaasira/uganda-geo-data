# Contributing to Uganda Geo

Uganda Geo maintains a framework-independent PHP package and a separately versioned collection of Uganda administrative data. Code contributions and corrections to administrative facts have different evidence requirements.

## Development setup

- Use an actively supported PHP version declared in `composer.json` and Composer 2.
- Run `composer install`, `./vendor/bin/phpunit`, `./vendor/bin/ecs check src tests` and `./vendor/bin/phpstan analyse src --level 9`.
- Keep runtime dependencies optional unless necessary. Document every public API addition or breaking change.
- Send changes through a focused pull request targeting the maintained integration branch. If unsure, check the active release policy before choosing a base branch.

## Pull requests

Include the problem, root cause, implementation decisions, before/after behavior, regression tests, compatibility effects and actual verification results. Add release notes for user-visible or data-semantic changes.

Keep commits independently understandable. A test, code fix or documentation adjustment should usually have its own logical commit, without splitting trivial edits purely to inflate commit counts.

## Correcting administrative data

Use the data-correction issue form and supply the affected unit IDs, level, parent chain, observed value, proposed change, source URL and evidence date. Name-only corrections are insufficient when multiple units share the same name. Distinguish a formal administrative change from a different source snapshot.

Do not delete source rows to reconcile counts. Preserve raw provenance and record explicit reviewed reconciliation decisions. Geometry submissions also need the coordinate reference system, geographic layer, source custodian and the applicable reuse terms.

## Security

Do not publish credentials or sensitive data in issues. Follow `SECURITY.md` for private reporting.
