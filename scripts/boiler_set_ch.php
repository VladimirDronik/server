<?php
/**
 * Скрипт изменения уставки ЦО
 */

require_once '../include.php';

$id = (isset($argv[1]) ? $argv[1] : null);
$boiler = new Boiler($id);
if (isset($boiler))
{
    if ($boiler->setParam('ch_setpoint_temp', $argv[2])) exit(0);
    else exit(1);
}
else exit(1);