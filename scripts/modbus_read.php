<?php

require_once '../include.php';

$value = Modbus::modbusRtu($argv[1], 'read', 5)['response'];
if (isset($value))
{
    echo $value;
    echo PHP_EOL;
    exit(0);
}
else exit(1);