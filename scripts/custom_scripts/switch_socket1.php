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
$object = new Objects();
$object->select(3);
$object->set_port_state($argv[1]);


if ($object->status =='on')
    $sendsring = '{"events":{"spalnya_rozetka": "on"}, "info": {"spalnya_rozetka": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"spalnya_rozetka": "off"}, "info": {"spalnya_rozetka": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);
