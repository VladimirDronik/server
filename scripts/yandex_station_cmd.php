<?php
/**
 * Скрипт отправки запроса Яндекс.Станции на выполнение команды
 */
require_once '../include.php';

$ya_station = new YandexStation ($argv[1]);
$ya_station->cmd($argv[2]);

// YandexStation::cmd($argv[1], $argv[2]);