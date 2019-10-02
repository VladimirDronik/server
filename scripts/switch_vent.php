<?php

/**
 * Скрипт включает или выключает нагрузку
 * $script->set(15,1, 1); включить нагрузку на 15 порту 1-го устройства
 * $script->set(15,0, 1); выключить нагрузку на 15 порту 1-го устройства
 * $script->set(15,2, 1); переключить нагрузку на 15 порту 1-го устройства
 */


require_once '../../include.php';


//Создали экземпляр класса объектов
$object = new Objects();

//Выбрали объект с которым будем работать, в данном случае лампочку
$object->select(10);

//Если второй параметр передаем 1 - значит меняем только состояние порта, если передаем 2,
//то меняем состояние порта и состояние объекта
if($argv[2]==1) {
    $object->set_port_state($argv[1]);
    $object->select(19);
    $object->setStatus($argv[1],false);
}
else {
    $object->setStatus($argv[1]);

}



if ($object->check_switch_state($argv[1])=='on')
    $sendsring = '{"events":{"ventilyaciya": "on"}, "info": {"ventilyaciya": "on"}, "status": ["propeller_off"]}';
else
    $sendsring = '{"events":{"ventilyaciya": "off"}, "info": {"ventilyaciya": "off"}}';

//Отправляем данные монитору демостенда
demostand::send($sendsring);

