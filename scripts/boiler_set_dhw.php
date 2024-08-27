<?php
/**
 * Скрипт изменения уставки ГВС
 */

require_once '../include.php';

$boiler = new Boiler($argv[1]);
$boiler->setParam('dhw_setpoint_temp', $argv[2]);