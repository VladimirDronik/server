<?php

require_once '../include.php';

$response = Modbus::modbusRtu($argv[1], 'write', null, $argv[2]);

if ($response && !$response['error']) exit(0);

exit(1);