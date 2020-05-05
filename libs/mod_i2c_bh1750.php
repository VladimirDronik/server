<?php
/*
* Copyright (c) 2016, Andrey_B
* http://ab-log.ru
* Подробнее см. LICENSE.txt или http://www.gnu.org/licenses/
*/

/*
Это драйвер для датчика датчика освещенности BH1750 для библиотеки I2C-PHP
*/

require_once("mod_i2c_lib.php");

function get_lux1750()
{
	i2c_init();
	i2c_start();

	i2c_send("46");
	i2c_send("10");
	i2c_stop();

	i2c_start();
	i2c_send("47");

	$msb = i2c_read();
	$lsb = i2c_read();
	//echo $msb.$lsb."\n";
	i2c_stop();


	if ( strlen($msb) == 1 )
	$msb = "0$msb";
	if ( strlen($lsb) == 1 )
	$lsb = "0$lsb";
	$msb = hexdec($msb);
	$lsb = hexdec($lsb);

	//$msb = 131;
	//$lsb = 144;

	$raw_lux = round((($msb << 8) | $lsb)/1.2);

	return $raw_lux;

}


