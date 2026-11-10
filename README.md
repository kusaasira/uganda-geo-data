# Uganda Geo Data

Uganda Geo is a framework-independent PHP library for administrative location data. It preserves distinct source IDs for districts, counties, subcounties, parishes and villages while exposing explicitly scoped searches. The historical bundled data was originally sourced from the Uganda passport portal. Official UBOS snapshots and source provenance are reviewed separately.

# Description

This package gives you the leverage to access all sub levels ranging from districts, counties, subcounties, parishes to villages in Uganda. You can also access the different mentioned areas independently.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Retrieve Districts data](#retrieve-districts-data)
  - [Retrieve Individual geo units data.](#retrieve-individual-geo-units-data)
- [Credits](#credits)
- [Contributions](#contributions)
- [License](#license)

## Requirements

In order to run this project, ensure that you have installed;

- PHP 8.2 or later for the planned v2 release (the currently published v1 tag has different requirements)
- Composer

## Installation

Install with Composer. **Important:** the currently published stable tag, `v1.0.0`, exposes the older `Uganda\Geo` API. The `Uganda\Uganda` examples below describe the development branch and must not be presented as installation instructions for that stable tag. Do not migrate production consumers until a compatible v2 tag is available.

```bash
composer require kusaasira/uganda-geo
```

## Usage

The examples below show examples of usage of the package and their resulting outputs

### Retrieve Districts data.

```php
use Uganda\Uganda;

$uganda = new Uganda();

# Retrieve all districts
$districts = $uganda->districts();

# Retrieve all counties in a particular district
$counties = $uganda
    ->district('Mukono')
    ->counties();

# Retrieve all sub counties in a particular district
$subCounties = $uganda
    ->district('Mukono')
    ->subCounties();


# Retrieve all parishes in a particular district
$parishes = $uganda
    ->district('Mukono')
    ->parishes();

# Retrieve all villages in a particular district
$villages = $uganda
    ->district('Mukono')
    ->villages();
```

### Retrieve County data.

```php
use Uganda\Uganda;

$uganda = new Uganda();

# Retrieve all counties
$counties = $uganda->counties();

# Retrieve specific county
$county = $uganda->county('Adjumani West County');

# Retrieve all sub counties in a particular county
$subCounties = $uganda
    ->county('Adjumani West County')
    ->subCounties();

# Retrieve all parishes in a particular county
$parishes = $uganda
    ->county('Adjumani West County')
    ->parishes();

# Retrieve all villages in a particular county
$villages = $uganda
    ->county('Adjumani West County')
    ->villages();
```

### Retrieve Sub County data.

```php
use Uganda\Uganda;

$uganda = new Uganda();

# Retrieve all sub counties
$subCounties = $uganda->subCounties();

# Retrieve specific sub county
$subCounty = $uganda->subCounty('Namasale Town Council');

# Retrieve all parishes in a particular sub county
$parishes = $uganda
    ->subCounty('Namasale Town Council')
    ->parishes();

# Retrieve all villages in a particular sub county
$villages = $uganda
    ->subCounty('Namasale Town Council')
    ->villages();
```

### Retrieve Parish data.

```php
use Uganda\Uganda;

$uganda = new Uganda();

# Retrieve all parishes
$parishes = $uganda->parishes();

# Retrieve specific parish
$parish = $uganda->parish('Bunamwamba');

# Retrieve all villages in a particular parish
$villages = $uganda
    ->parish('Bunamwamba')
    ->villages();
```

### Retrieve Village data.

```php
use Uganda\Uganda;

$uganda = new Uganda();

# Retrieve all villages
$villages = $uganda->villages();

# Retrieve specific village
$village = $uganda->village('Ayeye');
```

## Identity-safe queries (development branch)

Use source IDs for identity and parent-scoped searches for repeated place names:

```php
$district = $uganda->district('Mukono');
$sameDistrict = $uganda->findById('district', $district->id());
$subCounty = $uganda->subCounty('Namasale Town Council');
$parishes = $uganda->searchByName('parish', 'Central', $subCounty->id());
```

Name-only global lookups can raise `AmbiguousLocationException`. Collection keys in the next major release are source IDs rather than normalized place names. The historical numeric IDs are not established official UBOS codes. See [v1-to-v2 migration](docs/migration/v1-to-v2.md).

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
