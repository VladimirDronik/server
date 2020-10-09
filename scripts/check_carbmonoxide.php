<?php

/**
* Скрипт проверки датчика СО2
**/

require_once '../include.php';

$sensor = new CarbMonoxide($argv[1]);
$sensor->check();
