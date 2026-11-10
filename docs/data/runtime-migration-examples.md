# Migrating nested hierarchy access to ID keys

In the next major release, the hierarchy collections use numeric source IDs rather than lowercase names. The same keys are preserved in `toArray()` output.

Previously:

```php
$district = $uganda->districts()['bukomansimbi'];
$county = $district->counties()['some county'];
```

Use explicit source identity:

```php
$district = $uganda->district('Bukomansimbi');
$districtId = $district->id();
$district = $uganda->districts()[$districtId];
foreach ($district->counties() as $countyId => $county) {
    // Both the key and $county->id() identify the same source record.
}
```

Every record remains addressable by ID even if siblings share the same name. Nested JSON/array serialization preserves the ID keys. For API responses that require indexed arrays rather than JSON objects, consumers can explicitly call `array_values()` at their output boundary without changing canonical identity.

The numerical IDs belong to the historical package dataset. They are **not verified official UBOS codes**. Safe name matching and disambiguation are addressed by a separate scoped lookup API.
