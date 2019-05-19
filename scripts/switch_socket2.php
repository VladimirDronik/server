<?php

/**
 * Скрипт включает или выключает нагрузку
 * $script->set(15,1, 1); включить нагрузку на 15 порту 1-го устройства
 * $script->set(15,0, 1); выключить нагрузку на 15 порту 1-го устройства
 * $script->set(15,2, 1); переключить нагрузку на 15 порту 1-го устройства
 */


require_once '../../include.php';



//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(15);

if ($object->status == 'on')
    $sendsring = '{"events":{"gostin_rozetka": "on"}, "info": {"gostin_rozetka": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"gostin_rozetka": "off"}, "info": {"gostin_rozetka": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);