# Verified local GIS format exports

After generating **reviewed** canonical GeoJSON, export GIS-compatible layers:

    mkdir -p build
    php tools/geo/export-formats.php reviewed.geojson build/parish-formats

This optional developer tool requires a local GDAL installation providing
ogr2ogr. It does not install GDAL into the PHP package or run automatically.

The output contains normalized RFC 7946 GeoJSON, an EPSG:4326 GeoPackage,
a complete ESRI Shapefile (.shp, .shx, .dbf, .prj and .cpg), the 10-character
DBF field dictionary and an export metadata manifest.

All source features must carry reviewed canonical ID mappings. The exporter
rejects missing identities, duplicate units, malformed polygon coordinates
and export format round trips that lose IDs. GDAL normalizes geometric
representation, so coordinate byte identity is not asserted.

The export directory is atomically renamed from a private temporary stage
only after every expected format and round-trip check passes. Existing
outputs are never overwritten.

Export does NOT imply publication approval. The metadata explicitly retains
review-required rights and topology status. Source attribution and actual
redistribution rights must be settled before attaching these files to a
public release.
