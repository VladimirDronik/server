<?php

require_once '../include.php';

// $argv[1] - id объекта
// $argv[2] - значение яркости

$id = (isset($argv[1]) ? $argv[1] : null);
$brightness = (isset($argv[2]) ? $argv[2] : null);

$dali = new Dali($id);
if (isset($dali))
{
    if ($dali->setBrightness($brightness)) exit(0);
}

exit(1);