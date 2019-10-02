<?php

/**
 * Скрипт включения или выключения режима охраны
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();


//Выбрали объект с которым будем работать
$object->select(24);
$object->setStatus('on');
$status = $object->status;


if ($status=='on') {
//Выключаем всю нагрузку
    $object->select(1);
    $object->setStatus('off');

    $object->select(2);
    $object->setStatus('off');

    $object->select(6);
    $object->setStatus('off');

    $object->select(9);
    $object->setStatus('off');

    $object->select(11);
    $object->setStatus('off');

    $object->select(12);
    $object->setStatus('off');

    $object->select(15);
    $object->setStatus('off');

    $object->select(16);
    $object->setStatus('off');

    $object->select(17);
    $object->setStatus('off');

    $object->select(18);
    $object->setStatus('off');

    $object->select(19);
    $object->setStatus('off');

    $object->select(20);
    $object->setStatus('off');

    $object->select(21);
    $object->setStatus('off');

    $object->select(22);
    $object->setStatus('off');

}


if ($status == 'on')
    $sendsring = '{"events":{"window": "off", "door": "off"}, "info": {"home_lock_status": "off"}, "status": ["door", "window", "eco_mode", "house_locked"]}';
else
    $sendsring = '{"events":{"window": "on", "door": "on"}, "info": {"home_lock_status": "on"}, "status": ["normal_warm", "house_unlocked"]}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);



