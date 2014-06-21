Allegro-WebAPI-PHP-v2
=====================

Simple WebAPI Class for Allegro based on nusoap Library

## Requirements

nuSOAP library, [Download from here](http://sourceforge.net/projects/nusoap/)

## Usage

```php

require_once('nusoap/nusoap.php');
require_once('AllegroWebAPI.php');

$API = new AllegroWebAPI(true);

$API->connect('userLogin', 'userPassword', 'apiKey');

var_dump($API->doGetMyNotSoldItems());

```