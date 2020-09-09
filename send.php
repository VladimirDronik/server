<?php



require_once 'webSocket.php';
require_once 'include.php';


$socket = new webSocket();

$socket->address = '0.0.0.0:8000';

//Получение списка устройсв
//$socket->getDevices();

//Получение статуса для пользователя 10, устройства 123
//$socket->getStatus(10,46);

//Установка статуса для удаленного устройства 46
$socket->setStatus(46,'on');

