<?php
require_once '../include.php';

$id = (isset($argv[1]) ? $argv[1] : null);

if (null !== $sensor = new Sensor($id)) {
    $sensor->checkSensor();
    $sensor->launchRegulator();
}