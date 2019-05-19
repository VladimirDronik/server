<?php

/**
 * Скрипт включает или выключает лампочку 2
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(14);
//Установили объекту статус. Этот объект связан с выходным портом, поэтому на порту меняем состояние
$object->set_status($argv[1]);

if ($object->check_switch_state($argv[1])=='on')
    $sendsring = '{"events":{"gostin": "on"}, "info": {"gostin": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"gostin": "off"}, "info": {"gostin": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);


