# Point-in-polygon lookup

`Uganda\\Geo\\PointInPolygon::classify($longitude, $latitude, $geometry)` returns `inside`, `outside` or `boundary` for a GeoJSON Polygon or MultiPolygon in EPSG:4326. Longitude is first.

A coordinate inside an interior hole returns `outside`; on an exterior or hole edge it returns `boundary`. Multipart geometries are treated as the union of their polygons. The method does not silently assign a location when geometry is missing or ambiguous.

```php
$location = (new Uganda\Geo\PointInPolygon())->classify(32.59, 0.31, $polygon);
```

This is a point classification primitive, **not** an authoritative Uganda parish assignment. Always join geometries to validated canonical administrative IDs and report overlapping, unmapped or outdated features explicitly. Coordinates close to polygon edges may be affected by floating-point tolerance and source precision.
