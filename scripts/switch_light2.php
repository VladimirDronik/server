<?php

/**
 * Скрипт включает или выключает лампочку 2
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(5);
//Установили объекту статус. Этот объект связан с выходным портом, поэтому на порту меняем состояние
$object->setStatus($argv[1]);

if ($object->check_switch_state($argv[1])=='on')
    $sendsring = '{"events":{"spalnya": "on"}, "info": {"spalnya": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"spalnya": "off"}, "info": {"spalnya": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);


