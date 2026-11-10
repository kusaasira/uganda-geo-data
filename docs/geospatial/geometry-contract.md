# Spatial boundary contract

## Coordinate reference systems

A source GIS service may advertise EPSG:3857 (Esri 102100) or another projected coordinate reference. Preserve its actual source CRS in `source_crs` and retain the original response unchanged. Export normalized GeoJSON coordinates in **WGS84 EPSG:4326 with longitude first and latitude second**, using the service's `outSR=4326` query parameter or an audited transformation. Do not label raw Web Mercator metres as degrees. EPSG:4326 coordinates must be finite numbers within longitude ±180 and latitude ±90.

The canonical administrative ID and geometry ID are separate: boundaries can be reissued, revised, disputed or absent without changing the identity of the associated locality. `unit_id` may remain null until a documented, verified reconciliation succeeds.

## Polygon handling

- Accept GeoJSON `Polygon` and `MultiPolygon` with non-empty rings and closed exterior/interior rings.
- Reject missing, nonnumeric, nonfinite and out-of-range coordinate positions.
- Preserve coordinate ordering, polygon parts, interior holes, raw source attributes and acquisition source feature ID.
- Perform full topology tests (ring self-intersection, winding direction, sibling overlap, gaps, parent containment and repair audit) separately before an asset is marked verified.
- Treat border points, overlaps and missing source geometry as explicit edge cases for spatial lookup; do not infer a parish from a nearest centroid.
- Never fabricate a polygon to fill a coverage gap.

## Linked boundary fields

`resources/schemas/boundary.schema.json` describes `boundary_id`, `source_feature_id`, `source_id`, optional `unit_id`, source and output CRS, lifecycle verification, geometry and original feature attributes. It intentionally does not require every published administrative unit to have a mapped polygon.

## Current implementation boundary

`Uganda\\Geo\\GeometryContract` validates coordinate structure, geographic bounds and ring closure only. It is **not** a substitute for GIS topology validation, source-rights verification or authoritative administrative matching. Existing PHP lookup methods and dataset records are unchanged.

A source label containing `2024` is not evidence that boundaries were captured at a legal 2024 effective date. Keep acquisition time, reference date and legal effective date distinct.
