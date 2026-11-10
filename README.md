# Uganda Geo Data

Uganda Geo is a framework-independent PHP library for administrative location data. It preserves distinct source IDs for districts, counties, subcounties, parishes and villages while exposing explicitly scoped searches. The historical bundled data was originally sourced from the Uganda passport portal. Official UBOS snapshots and source provenance are reviewed separately.

# Description

This package gives you the leverage to access all sub levels ranging from districts, counties, subcounties, parishes to villages in Uganda. You can also access the different mentioned areas independently.

## Versions and requirements

The currently published stable package is **v1.0.0**. It exposes the chainable \`Uganda\Geo\` class. The forthcoming **v2.0.0** release is not published yet and introduces a different \`Uganda\Uganda\` object model.

- **Stable v1:** Follow the requirements and API of the existing immutable v1.0.0 tag.
- **Development v2:** Minimum PHP **8.2** and Composer 2. The PHP 8.2 branch reaches upstream end of security maintenance on 31 December 2026, so consumer deployments should plan an upgrade.

## Installation

### Published stable v1

\`\`\`bash
composer require "kusaasira/uganda-geo:^1.0"
\`\`\`

\`\`\`php
<?php

require 'vendor/autoload.php';

use Uganda\Geo;

// The v1 API is chainable and returns JSON using all().
$json = (new Geo())->districts('Mukono')->counties()->all();
echo $json;
\`\`\`

### Upcoming v2

After a stable v2 release is published, install it with:

\`\`\`bash
composer require "kusaasira/uganda-geo:^2.0"
\`\`\`

Until then, the examples below apply only to a checkout of the \`next/2.x\` integration branch with Composer dependencies installed. They are **not** compatible with the published v1 package.

## Development v2 API

\`\`\`php
<?php

use Uganda\Uganda;

$uganda = new Uganda();

// Access a district by name when the name is unambiguous.
$district = $uganda->district('Mukono');
$districtId = $district->id();

// Recover the same model using its stable package identity.
$matchingDistrict = $uganda->findById('district', $districtId);
assert($matchingDistrict === $district);

// Traverse direct descendants using the actual parent model.
foreach ($district->counties() as $county) {
    foreach ($county->subCounties() as $subCounty) {
        foreach ($subCounty->parishes() as $parish) {
            foreach ($parish->villages() as $village) {
                // Each model carries an ID and a name.
                echo $village->id(), ': ', $village->name(), PHP_EOL;
            }
        }
    }
}

// Lookups by name can have more than one result.
$matches = $uganda->searchByName('parish', 'Central');

// Scope the search to an existing parent when that parent is known.
$subCounty = $uganda->searchByName('subcounty', 'Namasale Town Council')[0] ?? null;
if ($subCounty !== null) {
    $localParishes = $uganda->searchByName('parish', 'Central', $subCounty->id());
}
\`\`\`

Global singular name methods such as \`$uganda->parish($name)\` throw an \`AmbiguousLocationException\` if multiple source units share that name. Use an ID or parent-scoped result set rather than accepting an arbitrary first match. Legacy numeric identifiers are **package source IDs**, not proven official government codes.

See the [v1-to-v2 migration guide](docs/migration/v1-to-v2.md) and [official source review](docs/data/official-source-review.md) for data provenance, breaking changes and known limitations.

## Credits

The original package dataset was derived from [Uganda's passport portal](https://passports.go.ug). Administrative counts in dated UBOS publications can differ from this legacy snapshot. See [official source review](docs/data/official-source-review.md) and [source registry](docs/data/sources.md) before treating a record as current or legally authoritative.

## Collaborators ✨

<!-- readme: collaborators -start -->
<table>
<tr>
    <td align="center">
        <a href="https://github.com/timek">
            <img src="https://avatars.githubusercontent.com/u/2828143?v=4" width="100;" alt="timek"/>
            <br />
            <sub><b>Tim3k</b></sub>
        </a>
    </td>
    <td align="center">
        <a href="https://github.com/kusaasira">
            <img src="https://avatars.githubusercontent.com/u/10392992?v=4" width="100;" alt="kusaasira"/>
            <br />
            <sub><b>Joshua Kusaasira</b></sub>
        </a>
    </td>
    <td align="center">
        <a href="https://github.com/Marshud">
            <img src="https://avatars.githubusercontent.com/u/63245157?v=4" width="100;" alt="Marshud"/>
            <br />
            <sub><b>Marshud</b></sub>
        </a>
    </td></tr>
</table>
<!-- readme: collaborators -end -->

## Contributors ✨

<!-- readme: contributors -start -->
<table>
<tr>
    <td align="center">
        <a href="https://github.com/kusaasira">
            <img src="https://avatars.githubusercontent.com/u/10392992?v=4" width="100;" alt="kusaasira"/>
            <br />
            <sub><b>Joshua Kusaasira</b></sub>
        </a>
    </td>
    <td align="center">
        <a href="https://github.com/RoadSigns">
            <img src="https://avatars.githubusercontent.com/u/5822139?v=4" width="100;" alt="RoadSigns"/>
            <br />
            <sub><b>Zack</b></sub>
        </a>
    </td></tr>
</table>
<!-- readme: contributors -end -->

## License

This package is free software distributed under the terms of the MIT license.
