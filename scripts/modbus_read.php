<?php

require_once '../include.php';

$value = Modbus::getRegisterValue($argv[1]);
echo $value . PHP_EOL;
return $value;