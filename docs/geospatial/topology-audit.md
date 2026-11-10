# Polygon topology diagnostics

`Uganda\\Geo\\PolygonTopologyAuditor` inspects GeoJSON Polygon and MultiPolygon features after structural validation by `GeometryContract`.

It reports zero-area rings, non-adjacent edge intersections and holes with any vertex outside the exterior or whose edges cross or touch its boundary. Holes must remain strictly within their enclosing exterior ring and must not cross, overlap or nest inside other holes. These situations produce a `hole_overlap` diagnostic. Independent MultiPolygon members are checked for interior containment and crossing exterior edges. A single corner contact is not treated as overlap. Intra-feature overlaps produce `multipart_overlap`. A valid feature reports `passed` and signed ring area in **degrees squared**, which must not be interpreted as land area.

This is intentionally a *feature-level diagnostic*. It does not establish administrative accuracy, overlap between separate administrative features, regional gaps, child-parent boundary containment or legal boundary validity. Those checks need a separate spatial QA tool and the canonical parent-ID reconciliation.

```php
$audit = (new Uganda\Geo\PolygonTopologyAuditor())->audit($geometry);
if ($audit['status'] !== 'passed') {
    // Quarantine this feature for GIS review.
}
```

Never modify original source rings silently to make a test pass. Keep a source checksum and an explicit repair record whenever verified geometry is corrected.
