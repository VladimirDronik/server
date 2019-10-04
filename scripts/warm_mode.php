<?php

/**
 * Скрипт включения или выключения эко режима
 */


require_once '../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Меняем режим отопления в настройках
system::set_setting('heating_mode', '$argv[1]');

//Меняем режим отопления у термостата, указываем название режима отопления и ид объекта, коотрым является термостат
thermostats::set_temperature_mode('$argv[1]',40);

if ($argv[1]=='eco'){

    $object->select(43);
    $object->setStatus('off');

    $object->select(45);
    $object->setStatus('off');

}

if ($argv[1]=='normal'){

    $object->select(43);
    $object->setStatus('off');

    $object->select(44);
    $object->setStatus('off');

}

if ($argv[1]=='night'){

    $object->select(44);
    $object->setStatus('off');

    $object->select(45);
    $object->setStatus('off');

}





