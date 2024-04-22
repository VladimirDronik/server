<?php

require_once '../include.php';

// $argv[1] - id modbus устройства
Modbus::checkModbusAvailible($argv[1]);