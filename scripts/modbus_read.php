<?php

require_once '../include.php';

$value = Modbus::modbusRtu($argv[1], 'read', null, true);

if (isset($value))
{
    echo $value . PHP_EOL;
    exit(0);
}

exit(1);
