<?php

require_once '../include.php';

// $argv[1] - id modbus устройства
$result =  Modbus::checkModbusAvailible($argv[1]);
// var_dump($result);
return $result;