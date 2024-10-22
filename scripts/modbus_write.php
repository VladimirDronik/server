<?php

require_once '../include.php';

if (isset($argv[2]))
{
    $response = Modbus::sendModbus($argv[1], 'write', $argv[2], true);

    if (isset($response))
    {
        echo $response . PHP_EOL;
        exit(0);
    }
}

exit(1);