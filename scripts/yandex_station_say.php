<?php
/**
 * Скрипт отправки запроса Яндекс.Станци на воспроизведение фразы
 */
require_once '../include.php';

var_dump ($argv);

$ya_station = new YandexStation ($argv[1]);
$ya_station->say($argv[2]);

// YandexStation::say($argv[1], $argv[2]);