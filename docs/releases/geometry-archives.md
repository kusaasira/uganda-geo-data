# Local geospatial archive preparation

Once reviewed GeoJSON has been converted to validated ESRI Shapefile and
GeoPackage output, package the prepared layer in a self-checking ZIP:

    php tools/release/package-geometry.php build/parish-formats v2.0.0 build/parishes-review.zip

Requires the optional PHP zip extension at release-build time. The
Composer runtime remains framework-independent and does not require ext-zip.

The output ZIP includes GeoJSON, GeoPackage, all five Shapefile sidecars,
field-name documentation, geometry metadata and an internal checksum
manifest. The archiver validates every included byte after writing, requires
EPSG:4326 CRS metadata and refuses existing output destinations.

Release CI also builds real GeoPackage and Shapefile files from a controlled
polygon-with-hole and multipolygon fixture using GDAL, then assembles two
independent archives and compares their bytes. Unit tests use synthetic file
content only to exercise the ZIP inventory and fail-closed metadata behavior.
The integration test proves this local packaging path, not nationwide data
accuracy or reproducibility across different GDAL versions.

**Do not publish this local review bundle directly.** The checksum
manifest is not evidence of valid national boundaries, sufficient mapped
parish coverage or source reuse permission. The archive explicitly
records release_asset_ready as false even if local source metadata says
publication_rights is permitted. The separate source-rights and release
acceptance gates must run before a public GitHub release attaches these
files.

Never ship third-party shape data from a public endpoint solely because the
endpoint is accessible. Record provenance and compatible attribution first.
