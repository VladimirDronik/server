<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);

$regulator = new Regulator($id);
if (isset($regulator->regulator)) $regulator->checkRegulator();