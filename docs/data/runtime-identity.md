# Runtime administrative identity and collection keys

Hierarchy collections are now indexed by **numeric source IDs** rather than lowercased place names. The change prevents duplicate-name siblings from silently replacing each other or hiding entire descendant branches.

```php
$uganda = new \Uganda\Uganda();
$district = $uganda->district('Bukomansimbi');
$byId = $uganda->districts()[$district->id()];
```

The collection keys of `districts()`, `District::counties()`, `County::subCounties()`, `SubCounty::parishes()` and `Parish::villages()` are now numeric IDs. Consumers indexing these arrays by name must migrate to ID-based access or explicit singular lookups.

Legacy singular name lookup methods still return the first match when a name is ambiguous. A subsequent change will introduce scoped lookup and explicit ambiguity handling. Do not use a first-match lookup as an identity guarantee.

The numeric IDs are **legacy package identifiers**, not verified UBOS codes. Canonical snapshot identifiers are a separate source-aware interchange contract.
