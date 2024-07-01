<?php

require_once '../include.php';

$value = Modbus::modbusRtu($argv[1], 'read', 5)['response'];
echo $value . PHP_EOL;
return $value;