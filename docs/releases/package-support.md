# Package installation and support policy

The next major version of Uganda Geo targets **PHP 8.2 or later**. This is a deliberate major-version compatibility change from the previous PHP 7.4 minimum.

## Standalone PHP

Install using Composer after the v2 release becomes available:

```bash
composer require kusaasira/uganda-geo:^2.0
```

The existing published `v1.0.0` tag uses a different public API from the development branch. Do not use the unreleased v2 code examples with that tag.

## Laravel integration

The package is framework-independent. It does not register a Laravel service provider, because no such provider is shipped in this distribution. Laravel projects may instantiate `Uganda\\Uganda` directly using Composer autoloading.

An optional Laravel adapter can be added separately, provided its provider is implemented and verified in a minimal framework application before discovery is registered.

## Dependency expectations

The runtime requires PHP and its JSON extension. Development-only tools should not become production dependencies.

Release validation must include clean installation in standalone PHP, tests across the declared PHP version range and verification of Packagist metadata against the published tag.
