<?php
/*
* Copyright (c) 2016, Andrey_B
* http://ab-log.ru
* Подробнее см. LICENSE.txt или http://www.gnu.org/licenses/
*/

/*
Скрипт для получения данных температуры и влажности датчика HTU21D
Использует драйвер HTU21D и библиотеку I2C-PHP
*/
require_once '../include.php';

define("SCL", "31");
define("SDA", "30");
define("MD", "http://192.168.88.14/sec/?");

// Вариант реализации I2C: 1 - полностью программный; 2 - частично аппаратный (прошивка 3.43beta1 и выше)
define("V", "2");


$temperature = get_htu21d_temperature();
echo "Temperature: ".$temperature."\n";

$humidity = get_htu21d_humidity();
echo "Humidity: ".$humidity."\n";

$hum_compensated = $humidity + (25 - $temperature) * -0.15;
echo "Humidity (compensated): ".$hum_compensated."\n";

