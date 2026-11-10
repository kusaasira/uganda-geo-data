# Static administrative explorer

Generate a local searchable browser using a specific canonical snapshot. This operation performs **no network calls** and does not publish a hosted service.

```bash
php tools/data/build-explorer.php tests/fixtures/canonical-snapshot.json build/explorer
php -S 127.0.0.1:8000 -t build/explorer
```

Open `http://127.0.0.1:8000` to search the prebuilt public index by name or canonical ID. Results preserve repeated names across distinct parents. Details display the known ancestry and snapshot identifier.

The index includes only IDs, names, levels and parent IDs from the supplied file. Client-side rendering uses DOM text nodes, avoids injecting untrusted HTML and caps the displayed result count to 200 while keeping full result counts visible.

This does not assert legal administrative accuracy, map coverage or freshness. It is an offline reference explorer and should only be published with a reviewed snapshot, source notice and current-data limitations.
