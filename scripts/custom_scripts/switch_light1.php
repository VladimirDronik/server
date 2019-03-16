<?php

/**
 * Скрипт включает или выключает нагрузку
 * $script->set(15,1, 1); включить нагрузку на 15 порту 1-го устройства
 * $script->set(15,0, 1); выключить нагрузку на 15 порту 1-го устройства
 * $script->set(15,2, 1); переключить нагрузку на 15 порту 1-го устройства
 */


require_once '../../include.php';


//$script = new Scripts();


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(4);
//Установили объекту статус.
$object->set_status($argv[1]);

if ($object->check_switch_state($argv[1])=='on')
    $sendsring = '{"events":{"spalnya": "on"}, "info": {"spalnya": "on"}, "statusmessage": ["light_off", "elect_on", "normal_warm", "house_unlocked"]}';
else
    $sendsring = '{"events":{"spalnya": "off"}, "info": {"spalnya": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);



//Установили новое значение для связанного с объектом порта
//$object->set_port_state($argv[1]);

// $object4->set_status('on'); - это для примера



/*
if ($argv[1]=='on'){ //Если функция вызвана с каким-то параметром (к примеру on или off)


    $script->set(15,1, 1); // Устанавливаем 3 порту значение 1
    $script->set(16,1, 1);
}
else
    $script->set(16,0, 1); // Устанавливаем 3 порту значение 0

*/
// Тут устанавливаем значение визуальному элементу в БД в зависимости от того, какое дейчтвие сделали
