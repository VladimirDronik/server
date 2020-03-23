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


function get_lux()
{
	i2c_init();

    i2c_start();
    i2c_send("94"); //94
    //i2c_send("03"); //94
    i2c_stop();

    i2c_start();
    i2c_send("95"); //94
    $high_value = i2c_read()."\n";
    i2c_send("00");
    i2c_stop();
/*
    i2c_start();
    i2c_send("94"); //94
    i2c_send("04"); //94
    i2c_stop();

    i2c_start();
    i2c_send("95"); //94
    echo $low_value = i2c_read()."\n";
    i2c_stop();
*/
   // $exponent = ($high_value & 0xF0) >> 4;
  //$mantissa = (($high_value & 0x0F) << 4) | ($low_value & 0x0F);
  //echo $luminance = pow(2, $exponent) * $mantissa * 0.045;
/*
    $mantisa = ((($high_value & 0x0F) << 4) );
    echo $mantisa = (((float)$mantisa) * 0.045);
*/
    $lux = hexdec($high_value);
    return $lux;

}


