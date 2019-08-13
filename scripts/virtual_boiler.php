<?php

/**
 * Скрипт включения или выключения виртуального котла и системы отопления в целом
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(24);

if ($object->status == 'on')
    $sendsring = '{"events":{"okno": "on"}, "info": {"okno": "on"}, "status": ["light_off"]}';
else
    $sendsring = '{"events":{"okno": "off"}, "info": {"okno": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);
