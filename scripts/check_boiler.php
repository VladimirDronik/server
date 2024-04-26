<?php
/**
 * Скрипт опроса котла отопления
 * Запускается по умолчанию раз в минуту. Можно запускать по любому другому событию.
 */

require_once '../include.php';

<<<<<<< HEAD
$id = (isset($argv[1]) ? $argv[1] : null);
$boiler = new Boiler($id);

$boiler->check();
=======
$boiler = new Boiler($argv[1]);
$boiler->checkBoiler();
>>>>>>> f42b87a (Работы по добавлению управления котлами по модбас)
