<?php
/**
 * Файл содержит ссылки на подключаемые классы для работы отдельных сриптов, запускаемых независимо откуда угодно
 * А также здесь описываем системные настройки и подключение к БД
 */

//Корневая папка для проекта
define("ROOT_DIR",  __DIR__);


//Переменная для вывода в неё служебной информации
$system_message = true;

$remotewebsocket = '188.120.233.76:48654';
$localwebsocket = '192.168.100.201:8000';
$localsocket = 'tcp://127.0.0.1:5678';


$dbname = 'smarthome';
$dbuser = 'smarthome';
$dbpass = 'Alli80ed!';


require_once __DIR__.'/classes/System.php';
require_once __DIR__.'/classes/Objects.php';
require_once __DIR__.'/classes/Megad.php';
require_once __DIR__.'/classes/Scripts.php';
require_once __DIR__.'/classes/Thermostats.php';
require_once __DIR__.'/classes/Views.php';
require_once __DIR__.'/classes/Users.php';
require_once __DIR__.'/classes/Demostand.php';
require_once __DIR__.'/classes/Action.php';

System::db_connect($dbname, $dbuser, $dbpass);
