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


//Отправляем данные монитору демостенда
//demostand::send('{"events":{"window": "off", "door": "off", "gostin": "on", "elect_on":"on"}, "info": {"home_lock_status": "on"}, "status": ["light_off", "elect_on", "normal_warm", "house_unlocked"]}');


