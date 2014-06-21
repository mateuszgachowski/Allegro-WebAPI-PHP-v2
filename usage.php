<?php

date_default_timezone_set('Europe/Warsaw');

require_once('nusoap/nusoap.php');
require_once('AllegroWebAPI.php');

$API = new AllegroWebAPI(true);

$API->connect('userLogin', 'userPassword', 'apiKey');

var_dump($API->doGetMyNotSoldItems());