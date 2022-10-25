# Uganda Geo Data

This is a PHP package that retrieves Uganda's districts with their respective counties, sub counties, parishes and villages in Uganda. This data has been scrapped off [Uganda's passport's official portal](https://passports.go.ug).

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

- PHP 7.4 or later
- Composer

## Installation

This project using composer.

```
$ composer require kusaasira/uganda-geo
```

## Usage

The examples below show examples of usage of the package and their resulting outputs

### Retrieve Districts data.

```php
<?php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();

# Retrieve all districts
$all_districts = $geo->districts()->all();
echo $all_districts;

# Retrieve all counties in particular ditrict
$counties = $geo
                ->districts('Mukono')
                ->counties()->all();
echo $counties;

# Retrieve all sub counties under county in particular ditrict
$sub_counties = $geo
                ->districts('Mukono')
                ->counties('Mukono Municipality')
                ->sub_counties()->all();
echo $sub_counties;

# Retrieve all parishes in a sub county under county in particular ditrict
$parishes = $geo
                ->districts('Mukono')
                ->counties('Mukono Municipality')
                ->sub_counties('Goma Division')
                ->parishes()->all();
echo $parishes;

# Retrieve villages in a parish under a sub county under county in particular ditrict
$villages = $geo
                ->districts('Mukono')
                ->counties('Mukono Municipality')
                ->sub_counties('Goma Division')
                ->parishes('Nantabulirwa Ward')
                ->villages()->all();
echo $villages;
```

### Retrieve Individual geo units data.

#### Retrieve all districts

```php
# Query Districts
<?php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();
$all_districts = $geo->districts()->all();
echo $all_districts;
```

```json
{
  "districts": {
    "count": 135,
    "data": [
      {
        "id": "98",
        "name": "Abim"
      },
      {
        "id": "68",
        "name": "Adjumani"
      },
      {
        "id": "23",
        "name": "Agago"
      },
      ...
    ]
  }
}
```

### Retrieve all counties in Uganda

```php
<?php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();
$counties = $geo->counties()->all();
echo $counties;
```

```json
{
  "counties": {
    "count": 303,
    "data": [
      {
      "id": "242",
      "name": "Labwor County",
      "district": "98"
      },
      {
      "id": "166",
      "name": "Adjumani East County",
      "district": "68"
      },
      {
      "id": "165",
      "name": "Adjumani West County",
      "district": "68"
      },
      ...
    ]
  }
}
```

### Retrieve all sub counties in Uganda

```php
<?php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();
$sub_counties = $geo->sub_counties()->all();
echo $sub_counties;
```

```json
{
  "sub_counties": {
    "count": 2120,
    "data": [
      {
      "id": "242",
      "name": "Labwor County",
      "district": "98"
      },
      {
      "id": "166",
      "name": "Adjumani East County",
      "district": "68"
      },
      {
      "id": "165",
      "name": "Adjumani West County",
      "district": "68"
      },
      ...
    ]
  }
}
```

### Retrieve all parishes in Uganda

```php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();
<?php
$parishes = $geo->parishes()->all();
echo $parishes;
```

```json
{
  "parishes": {
    "count": 10365,
    "data": [
      {
      "id": "9127",
      "name": "Abongepach",
      "subcounty": "1546"
      },
      {
      "id": "9150",
      "Name": "Adwal",
      "subcounty": "1546"
      },
      {
      "id": "7279",
      "name": "Aninata",
      "subcounty": "1546"
      },
      ...
    ]
  }
}
```

### Retrieve villages in Uganda

```php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();
<?php
$villages = $geo->villages()->all();
echo $villages;
```

```json
{
  "villages": {
    "count": 71250,
    "data": [
      {
        "id":"57217",
        "name":"ABONGEPACH",
        "parish":"9127"
      },
      {
        "id":"58161",
        "name":"AMITA PRISON",
        "parish":"9127"
      },
      {
        "id":"58171",
        "name":"AMONICEK",
        "parish":"9127"
      },
      ...
    ]
  }
}
```

## Credits

The data used in this package has been scrapped off This data has been scrapped off [Uganda's passport's official portal](https://passports.go.ug) as there is no updated geo data source since 2018 published anywhere.

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
