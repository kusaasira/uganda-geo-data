# Navigating administrative ancestry

Location names are not unique. The next major API exposes ID-based relations without requiring consumers to guess which duplicate name belongs to a parent.

```php
$uganda = new Uganda\Uganda();
$parishes = $uganda->childrenOf('parish', $subCountyId);
$ancestors = $uganda->ancestorsOf('village', $villageId);
```

`childrenOf($level, $parentId)` returns direct children of the specified level, in source order. A district cannot be queried as a child because the bundled hierarchy has no district parent. Unknown parents return an empty list.

`ancestorsOf($level, $id)` returns the root district first and the immediate parent last. The location itself is excluded. Unknown unit IDs return an empty list. Missing parent relationships for an existing unit are treated as invalid data.

Both methods return the same underlying location instances returned by `findById`. IDs refer to the legacy package dataset and must not be assumed to be official administrative codes.
