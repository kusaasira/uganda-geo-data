# Uganda Geo Data

This is a PHP package that retrieves Uganda's districts with their respective counties, sub counties, parishes and villages in Uganda. This data has been scrapped off [Uganda's passport's official portal](https://passports.go.ug).

# Description

This package gives you the leverage to access all sub levels ranging from districts, counties, subcounties, parishes to villages in Uganda. You can also access the different mentioned areas independently.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Getting All Records](#getting-records)
  - [Getting Specific Records](#getting-specific-records)
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

```php
<?php
require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();

# Retrieve all districts
$all_districts = $geo->districts()->all();
echo $all_districts;

# Retrieve all counties in Uganda
$counties = $geo->counties()->all();
echo $counties;

# Retrieve all sub counties in Uganda
$sub_counties = $geo->sub_counties()->all();
echo $sub_counties;

# Retrieve all parishes in Uganda
$parishes = $geo->parishes()->all();
echo $parishes;

# Retrieve villages in Uganda
$villages = $geo->villages()->all();
echo $villages;
```

## Credits

The data used in this package has been scrapped off This data has been scrapped off [Uganda's passport's official portal](https://passports.go.ug) as there is no updated geo data source since 2018 published anywhere.

## Collaborators ✨

<!-- readme: collaborators -start -->
<!-- readme: collaborators -end -->

## Contributors ✨

<!-- readme: contributors -start -->
<!-- readme: contributors -end -->

## License

This package is free software distributed under the terms of the MIT license.
