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
$VPN_server = '10.35.0.254';

$host = 'localhost';
$dbname = 'smarthome';
$dbuser = 'smarthome';
$dbpass = 'Alli80ed!';


require_once __DIR__.'/classes/System.php';
require_once __DIR__.'/classes/Device.php';
require_once __DIR__.'/classes/Objects.php';
require_once __DIR__.'/classes/Megad.php';
require_once __DIR__.'/classes/Scripts.php';
require_once __DIR__.'/classes/Thermostats.php';
require_once __DIR__.'/classes/Hygrostats.php';
require_once __DIR__.'/classes/Views.php';
require_once __DIR__.'/classes/Users.php';
require_once __DIR__.'/classes/Demostand.php';
require_once __DIR__.'/classes/Action.php';
require_once __DIR__.'/classes/Graphs.php';
require_once __DIR__.'/classes/Count.php';
require_once __DIR__. '/classes/Dimmer.php';
require_once __DIR__. '/classes/Usensors.php';
require_once __DIR__. '/classes/SendSocket.php';
require_once __DIR__ . '/classes/Messages.php';
require_once __DIR__. '/classes/Lightstats.php';
require_once __DIR__. '/classes/Motionsensor.php';
require_once __DIR__. '/classes/HitePro.php';
require_once __DIR__. '/classes/CarbMonoxide.php';
require_once __DIR__. '/classes/Lamps.php';
require_once __DIR__. '/classes/Virtuals.php';
require_once __DIR__. '/classes/Relays.php';
require_once __DIR__. '/classes/Cameras.php';
require_once __DIR__. '/classes/Boiler.php';
require_once __DIR__. '/classes/Curtain.php';
require_once __DIR__. '/classes/Lock.php';
require_once __DIR__. '/classes/Events.php';
require_once __DIR__. '/classes/YandexStation.php';
require_once __DIR__. '/libs/YandexTTS.php';

//i2c drivers
require_once __DIR__.'/libs/mod_i2c_htu21d.php';
require_once __DIR__.'/libs/mod_i2c_MAX44009.php';
require_once __DIR__.'/libs/mod_i2c_bh1750.php';


//Classes for user scripts
require_once __DIR__ . '/classes/Scriptlang/MyObject.php';
require_once __DIR__ . '/classes/Scriptlang/MyRelay.php';
require_once __DIR__ . '/classes/Scriptlang/MySocket.php';


System::dbConnect($host, $dbname, $dbuser, $dbpass);
