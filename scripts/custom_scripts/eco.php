<?php

/**
 * Скрипт включения или выключения эко режима
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Меняем режим отопления в настройках
system::set_setting('heating_mode', 'eco');

//Меняем режим отопления у термостата, указываем название режима отопления и ид объекта, коотрым является термостат
thermostats::set_temperature_mode('eco',7);


//Выбрали объект с которым будем работать
$object->select(23);

if ($object->status == 'on')
    $sendsring = ' "statusmessage": ["light_off"]}';
else
    $sendsring = ' "statusmessage": ["normal_warm"]}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);



