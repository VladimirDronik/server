<?php
/**
 * Скрипт открытия шторы на процент
 */
require_once '../include.php';
$curtain = new Curtain($argv[1]);
$curtain->openPercent($argv[2]);