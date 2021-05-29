<?php
/**
 * Скрипт открытия шторы
 */
require_once '../include.php';

Lock::open($argv[1]);
