<?php

/**
 * Скрипт включения или выключения виртуального кондиционера
 */


require_once '../../include.php';



//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(16);

if ($object->status == 'on')
    $sendsring = '{"events":{"gostin_kondic": "on"}, "info": {"gostin_kondic": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"gostin_kondic": "off"}, "info": {"gostin_kondic": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);