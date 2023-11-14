<?php
/**
 * Скрипт получения снапшотов из rtsp канала видеорегистратора
 * Запускается по умолчанию раз в 15 минут
 */


require_once '../include.php';

$recorder_id = (isset($argv[1]) ? $argv[1] : null);
Cameras::getRtspSnapshots($recorder_id);