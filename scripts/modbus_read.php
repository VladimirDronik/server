<?php

require_once '../include.php';

$rId = $argv[1];

$response = Modbus::send($rId, 'read');


if (isset($response))
{
    echo $response . PHP_EOL;
    exit(0);
}

exit(1);