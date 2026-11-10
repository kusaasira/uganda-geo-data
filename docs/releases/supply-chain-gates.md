# Release supply-chain controls

Generate a deterministic CycloneDX runtime SBOM:

    mkdir -p build
    php tools/release/create-sbom.php 2.0.0-dev build/sbom.json

The inventory comes from composer.json plus resolved production lock
packages when third-party runtime dependencies exist. It excludes
development-only tools and labels PHP extension requirements as constraints,
not observed installed versions. It does not claim an external vulnerability
audit was performed.

Before distributing any third-party GIS geometry, validate the selected
sources against the project's registry:

    php tools/release/check-source-publication.php resources/sources.json arcgis-uganda-parishes-2024

Until licensing evidence is recorded this command correctly fails, even
though publicly reachable endpoints are eligible for development ingestion.
Successful ingestion is not redistribution authorization.

Publishing remains disabled while any required source is unverified or
restricted. This CLI deliberately does not upload assets, tag a release or
change source registry data.
