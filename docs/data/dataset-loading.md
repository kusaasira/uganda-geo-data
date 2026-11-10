# Administrative dataset loading errors

The hierarchy constructor no longer treats corrupt or missing data as an empty geographic hierarchy.

- The loader rejects unreadable or absent files.
- JSON syntax errors, non-list payloads, empty required tables and invalid record fields raise a `RuntimeException` with file or row context.
- An optional source directory on `new Uganda($directory)` allows isolated testing and controlled administrative snapshots.
- A valid empty lookup result must never be confused with a dataset loading error.

The default constructor still uses the bundled `src/Uganda/Data` files and does not alter the public lookup contract. This adds explicit exceptions when a broken installation previously yielded misleading empty results.

```php
try {
    $uganda = new \Uganda\Uganda();
} catch (\\RuntimeException $exception) {
    // Surface the error and repair the dataset; do not claim there are zero districts.
}
```
