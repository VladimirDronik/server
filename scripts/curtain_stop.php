<?php
/**
 * Скрипт закрытия шторы
 */
require_once '../include.php';
$curtain = new Curtain($argv[1]);
$curtain->stop();
