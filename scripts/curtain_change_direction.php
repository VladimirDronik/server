<?php
/**
 * Скрипт открытия шторы на процент
 */
require_once '../include.php';

// $argv[1] - id объекта шторы
// $argv[2] = 0 - мотор слева
// $argv[2] = 1 - мотор справа
$id = (isset($argv[1]) ? $argv[1] : null);
$dir = (isset($argv[2]) ? $argv[2] : null);
$curtain = new Curtain($id);
$curtain->changeDirection($dir);