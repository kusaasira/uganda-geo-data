# Parent-scoped and identity-safe location search

`Uganda\\Uganda::findById($level, $id)` retrieves a source-identified unit. `searchByName($level, $name, $parentId = null)` returns **every matching unit** for an administrative level, optionally restricted by its immediate parent ID.

Supported levels: `district`, `county`, `subcounty`, `parish` and `village`.

```php
$uganda = new \\Uganda\\Uganda();
$candidates = $uganda->searchByName('county', 'Lower Madi');
$byLegacyId = $uganda->findById('county', 309);
$scoped = $uganda->searchByName('county', 'Lower Madi', $districtId);
```

Singular methods on the root and on nested levels now raise `AmbiguousLocationException` when more than one record matches. They retain their existing not-found exception classes on zero matches. This **changes the previous arbitrary-first-match behavior** and requires major-version migration for ambiguous callers.

Search indexes are built lazily. Names are normalized for exact matching by case and surrounding whitespace, but never used as identity keys. Numeric IDs are legacy package source identifiers and not verified UBOS codes.
