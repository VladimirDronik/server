<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);
$ac = new Conditioner ($id);

if (isset($ac)) {
    if (isset($argv[2]))
        if ($ac->setAcTemperature($argv[2]))
            exit(0);
}

exit(1);