<?php
/**
 * Скрипт открытия шторы на процент
 */
require_once '../include.php';

// $argv[1] - id объекта шторы
// $argv[2] = 0 - мотор слева
// $argv[2] = 1 - мотор справа
$curtain = new Curtain($argv[1]);
$curtain->changeDirection($argv[2]);