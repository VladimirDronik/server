<?php
/**
 * Скрипт получения снапшотов из rtsp канала видеорегистратора
 * Запускается по умолчанию раз в 15 минут
 */


require_once '../include.php';

Cameras::getRtspSnapshots();