<?php

/**
 * Скрипт включает или выключает нагрузку
 * $script->set(15,1, 1); включить нагрузку на 15 порту 1-го устройства
 * $script->set(15,0, 1); выключить нагрузку на 15 порту 1-го устройства
 * $script->set(15,2, 1); переключить нагрузку на 15 порту 1-го устройства
 */


require_once '../../include.php';


$script = new Scripts();



//Выбрали объект с которым будем работать
$object4 = new Objects();
$object4->select(5);

//Установили объекту новый статус который взяли из аргумента вызванного скрипта или написали вручную
$object4->set_status($argv[1]);
// $object4->set_status('on'); - это для примера

//Отправляем данные монитору демостенда
//demostand::send('{"events":{"window": "off", "door": "off", "gostin": "on", "elect_on":"on"}, "info": {"home_lock_status": "on"}, "status": ["light_off", "elect_on", "normal_warm", "house_unlocked"]}');


