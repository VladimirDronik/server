<?php
/**
 * Скрипт закрытия шторы
 */
require_once '../include.php';
$id = (isset($argv[1]) ? $argv[1] : null);
$curtain = new Curtain($id);
$curtain->stop();
