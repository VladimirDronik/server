<?php
/**
 * Скрипт изменения уставки ЦО
 */

require_once '../include.php';

$boiler = new Boiler($argv[1]);
$boiler->setParam('ch_setpoint_temp', $argv[2]);