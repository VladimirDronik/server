<?php

require_once '../include.php';

// $argv[1] - id объекта
// $argv[2] - значение цветовой температуры


$id = (isset($argv[1]) ? $argv[1] : null);
$cct = (isset($argv[2]) ? $argv[2] : null);

$dali = new Dali($id);
if (isset($dali))
{
    if ($dali->setColorTemperature($cct)) exit(0);
}

exit(1);