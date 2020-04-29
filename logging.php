<?php
/**
 * Скрипт логгирования. Вызывается ассинхронно при работе системы, что бы не тормозить весь процесс.
 */

require_once 'include.php';

$date = date('Y-m-d H:i:s', time());

$typeLog = $argv[1];
$string = $argv[2];
$loggingPoint = $argv[3];

if (system::loggingPoint($loggingPoint)) {


    if (system::readSetting('logging') == 'file') {

        $file = ROOT_DIR . '/server.log';
        file_put_contents($file, $date . '___' . $typeLog . ':   ' . $string . "\n", FILE_APPEND | LOCK_EX);

    } else
        system::$db->query("INSERT INTO `logs` (`id`, `date`, `type`, `message`)
                                  VALUES (NULL, '$date', '$typeLog', '$string');");

}