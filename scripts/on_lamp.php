<?php
/**
 * Скрипт включения лампы
 */

require_once '../include.php';

$lamp = new Lamps($argv[1]);

/**
 * @params string whence - откуда вызвали скрипт
 * @params id - объект, который вызвал скрипт
 */
$lamp->on();