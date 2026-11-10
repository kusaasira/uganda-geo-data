# Scoped administrative name searches

Locations are identified by IDs. Display names are **not unique** across Uganda and can repeat even among children of the same parent.

Use `Uganda::searchByName($level, $name, $parentId = null)` to retrieve all matches, ordered by their original administrative source list. Supported levels are `district`, `county`, `subcounty`, `parish` and `village`.

```php
$uganda = new \\Uganda\\Uganda();
$matches = $uganda->searchByName('county', 'Lower Madi');
$withinDistrict = $uganda->searchByName('county', 'Lower Madi', $districtId);
```

The optional third argument filters by the **immediate parent source ID**, not a globally unique textual name. The existing singular global methods now throw `AmbiguousAdministrativeNameException` when more than one match is present rather than silently returning the first one. They continue to throw the existing not-found exceptions for missing names.

**Major-version behavior change:** code using `county($name)`, `parish($name)` or `village($name)` must disambiguate repeated names through parent-scoped results or canonical IDs. Direct child model methods are unchanged in this change and should not be used to resolve sibling collisions.
