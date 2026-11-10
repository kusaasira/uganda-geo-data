# Package installation and support policy

Stable Uganda Geo **2.0.0** requires **PHP 8.2 or later**. This is a deliberate major-version compatibility change from the previous PHP 7.4 minimum.

## Standalone PHP

Install the stable release using Composer:

```bash
composer require kusaasira/uganda-geo:^2.0
```

The immutable `v1.0.0` tag uses a different public API from v2. Do not copy v2 object examples into a v1 integration. Follow `docs/migration/v1-to-v2.md`.

## Laravel integration

The package is framework-independent. It does not register a Laravel service provider, because no such provider is shipped in this distribution. Laravel projects may instantiate `Uganda\\Uganda` directly using Composer autoloading.

An optional Laravel adapter can be added separately, provided its provider is implemented and verified in a minimal framework application before discovery is registered.

## Dependency expectations

The runtime requires PHP and its JSON extension. Development-only tools should not become production dependencies.

Release validation must include clean installation in standalone PHP, tests across the declared PHP version range and verification of Packagist metadata against the published tag. The main CI matrix verifies PHP 8.2, 8.3, 8.4 and 8.5. Tagged installations are checked separately against Packagist rather than a path-repository substitute.
