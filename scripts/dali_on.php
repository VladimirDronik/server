<?php

require_once '../include.php';

// $argv[1] - id объекта

$id = (isset($argv[1]) ? $argv[1] : null);

$dali = new Dali($id);
if (isset($dali))
{
    if ($dali->daliOn()) exit(0);
}

exit(1);