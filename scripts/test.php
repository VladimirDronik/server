<?php
//тестовый скрипт для всякой всячины

require_once '../include.php';
Device::checkAvailible('devices');




//System::addLog('message', 'сработал порт устройства '.$_SERVER['REMOTE_ADDR'].': '.$pt.', click='.$click.', long='.$long, 'port');

//Тестовая отправка данных эмулирующая клиента
//$view = new Views();
//echo $view->getGroupItems();
//echo $view->getRoomItems(10);
/*
$items = array(array('id' => '5',
    'type' => 'dimmer',
    'name' => 'диммер',
    'status' => 'on',
    'value' => '75'
    ));

$json = json_encode(array('status' => 'itemChange', 'items'=> $items));

$view->resData($json);
*/

//$view = new Views();
//echo $view->getGroupItems();
//echo $view->getRoomItems(55);


//Работа с диммерами
//$dimmer = new Dimmer(71);
//$dimmer->setSpeed(2); //Задаем значение скорости изменения значения диммера
//$dimmer->setValue(0); //Задаем значение диммера в процентах
//$dimmer->getValue(); //Считываем текущее значение диммера при каждом отпускании физической кнопки

//Action::runAction(58);


