<?php

require_once '../include.php';

// $argv[1] - id объекта
$ac_id = (isset($argv[1]) ? $argv[1] : null);
$ac = new Conditioner ($ac_id);
$ac->setAcPower('on');