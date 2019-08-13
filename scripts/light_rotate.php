<?php
/**
 * Скрипт запускается вечером, если никого нет дома и включена охранная сигнализация
 */


require_once '../../include.php';


$script = new Scripts();

//Массив с портами на которых висят лампочки

$light_array = array(16,18,19,20,27,28);


$now = strtotime(date('H:i'));
$start = strtotime(date('17:00'));
$end = strtotime(date('22:00'));


//Если сейчас вечер
if (($now>$start)&($now<$end)) {

//выключаем весь свет
file_get_contents("http://192.168.88.14/sec/?cmd=a:0");

//берем рандомом любой порт и включаем его

$random_element = array_rand($light_array, 1);

$script->set($light_array[$random_element],1, 1);

}





