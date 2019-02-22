<?php
/**
 * Файл содержит ссылки на подключаемые классы для работы отдельных сриптов, запускаемых независимо откуда угодно
 * А также здесь описываем системные настройки и подключение к БД
 */

$localsocket = 'tcp://127.0.0.1:5678';
$dbname = 'smarthome';
$dbuser = 'smarthome';
$dbpass = 'smartpaswd';

require_once __DIR__.'/classes/System.php';
require_once __DIR__.'/classes/Objects.php';
require_once __DIR__.'/classes/Megad.php';
require_once __DIR__.'/classes/Scripts.php';
require_once __DIR__.'/classes/Thermostats.php';
require_once __DIR__.'/classes/Views.php';
require_once __DIR__.'/classes/Users.php';
require_once __DIR__.'/classes/Demostand.php';

System::db_connect($dbname, $dbuser, $dbpass);
