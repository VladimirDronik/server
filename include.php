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

$host = getenv('MYSQL_HOST');
$dbname = getenv('MYSQL_DATABASE');
$dbuser = getenv('MYSQL_USER');
$dbpass = getenv('MYSQL_PASSWORD');

require_once __DIR__. '/classes/System.php';
require_once __DIR__. '/classes/Device.php';
require_once __DIR__. '/classes/Objects.php';
require_once __DIR__. '/classes/Megad.php';
require_once __DIR__. '/classes/Scripts.php';
require_once __DIR__. '/classes/Thermostats.php';
require_once __DIR__. '/classes/Hygrostats.php';
require_once __DIR__. '/classes/Views.php';
require_once __DIR__. '/classes/Users.php';
require_once __DIR__. '/classes/Demostand.php';
require_once __DIR__. '/classes/Action.php';
require_once __DIR__. '/classes/Graphs.php';
require_once __DIR__. '/classes/Count.php';
require_once __DIR__. '/classes/Dimmer.php';
require_once __DIR__. '/classes/Conditioner.php';
require_once __DIR__. '/classes/Usensors.php';
require_once __DIR__. '/classes/SendSocket.php';
require_once __DIR__. '/classes/Messages.php';
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
require_once __DIR__. '/classes/Page.php';
require_once __DIR__. '/classes/YandexStation.php';
require_once __DIR__. '/classes/Tape.php';
require_once __DIR__. '/classes/Labels.php';
require_once __DIR__. '/classes/Modbus.php';
require_once __DIR__. '/classes/Dali.php';
require_once __DIR__. '/libs/YandexTTS.php';
require_once __DIR__. '/classes/Pressurestat.php';
require_once __DIR__. '/classes/CarbDioxide.php';
require_once __DIR__. '/classes/Mqtt.php';
require_once __DIR__. '/classes/BeanstalkQueue.php';
require_once __DIR__. '/classes/Rs485.php';

//i2c drivers
require_once __DIR__.'/libs/mod_i2c_htu21d.php';
require_once __DIR__.'/libs/mod_i2c_MAX44009.php';
require_once __DIR__.'/libs/mod_i2c_bh1750.php';

//Classes for RS485 protocols
require_once __DIR__ . '/classes/Rs485Protocols/Onviz.php';
require_once __DIR__ . '/classes/Rs485Protocols/Aok.php';

//Classes for user scripts
require_once __DIR__ . '/classes/Scriptlang/MyObject.php';
require_once __DIR__ . '/classes/Scriptlang/MyRelay.php';
require_once __DIR__ . '/classes/Scriptlang/MySocket.php';

// ModbusTCP library
require_once __DIR__.'/libs/modbus/ModbusTcp.php';
require_once __DIR__. '/libs/modbus/ModbusFunctions.php';

require_once  __DIR__.'/vendor/autoload.php';

System::dbConnect($host, $dbname, $dbuser, $dbpass);
