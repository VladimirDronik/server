<?php

/*
Скрипт для получения данных температуры и влажности датчика HTU21D
Использует драйвер HTU21D и библиотеку I2C-PHP
*/
require_once '../include.php';


Usensors::checkI2C($argv[1]);


