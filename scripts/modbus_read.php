<?php

require_once '../include.php';

$response = Modbus::sendModbus($argv[1], 'read');

if (isset($response))
{
    echo $response . PHP_EOL;
    exit(0);
}

exit(1);
