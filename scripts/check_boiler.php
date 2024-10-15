<?php
/**
 * Скрипт опроса котла отопления
 * Запускается по умолчанию раз в минуту. Можно запускать по любому другому событию.
 */

require_once '../include.php';

$id = (isset($argv[1]) ? $argv[1] : null);
$boiler = new Boiler($id);
$boiler->debug = true;
$boiler->checkBoiler();
