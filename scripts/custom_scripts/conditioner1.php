<?php

/**
 * Скрипт включения или выключения виртуального кондиционера
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(6);

if ($object->status == 'on')
    $sendsring = '{"events":{"spalnya_kondic": "on"}, "info": {"spalnya_kondic": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"spalnya_kondic": "off"}, "info": {"spalnya_kondic": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);