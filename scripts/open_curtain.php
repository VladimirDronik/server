<?php
/**
 * Скрипт открытия шторы
 */
require_once '../include.php';
$curtain = new Curtain($argv[1]);
$curtain->open();
