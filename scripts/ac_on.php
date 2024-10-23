<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);
$ac = new Conditioner($id);

if (isset($ac)) {
    if ($ac->setAcPower('on')) exit(0);
}

exit(1);