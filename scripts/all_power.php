<?php

/**
 * Скрипт включения или выключения всей нагрузки
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(23);

if ($object->status == 'on')
    $sendsring = '{"events":{"okno": "on"}, "info": {"okno": "on"}, "statusmessage": ["light_off"]}';
else
    $sendsring = '{"events":{"okno": "off"}, "info": {"okno": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);



