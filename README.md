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
    ->county('Namasale Town Council')
    ->parishes();

# Retrieve all villages in a particular sub county
$villages = $uganda
    ->county('Namasale Town Council')
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
    ->county('Bunamwamba')
    ->villages();
```

### Retrieve Village data.

```php
use Uganda\Uganda;

$uganda = new Uganda();

# Retrieve all villages
$villages = $uganda->villages();

# Retrieve specific village
$subCounty = $uganda->village('Ayeye');
```

## Credits

The data used in this package has been scrapped off This data has been scrapped off [Uganda's passport's official portal](https://passports.go.ug) as there is no updated geo data source since 2018 published anywhere.

## Collaborators ✨

<!-- readme: collaborators -start -->
<table>
<tr>
    <td align="center">
        <a href="https://github.com/RoadSigns">
            <img src="https://avatars.githubusercontent.com/u/5822139?v=4" width="100;" alt="RoadSigns"/>
            <br />
            <sub><b>Zack</b></sub>
        </a>
    </td></tr>
</table>
<!-- readme: collaborators -end -->

## Contributors ✨

<!-- readme: contributors -start -->
<table>
<tr>
    <td align="center">
        <a href="https://github.com/RoadSigns">
            <img src="https://avatars.githubusercontent.com/u/5822139?v=4" width="100;" alt="RoadSigns"/>
            <br />
            <sub><b>Zack</b></sub>
        </a>
    </td>
    <td align="center">
        <a href="https://github.com/kusaasira">
            <img src="https://avatars.githubusercontent.com/u/10392992?v=4" width="100;" alt="kusaasira"/>
            <br />
            <sub><b>Joshua Kusaasira</b></sub>
        </a>
    </td></tr>
</table>
<!-- readme: contributors -end -->

## License

This package is free software distributed under the terms of the MIT license.
