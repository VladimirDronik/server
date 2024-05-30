<?php

require_once '../include.php';

// $argv[1] - id объекта
$ac = new Conditioner ($argv[1]);
$ac->setAcVDir($argv[2]);