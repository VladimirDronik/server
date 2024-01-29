<?php
/**
 * Скрипт удаляет старые логи. Запускается раз в день по расписанию.
 */

require_once '../include.php';

System::deleteLogs();

$output = null;
exec ("ls ".ROOT_DIR."/logs | grep modbus | wc -l", $output);
$i = $output[0];
while ($i !=0)
{
    if ($i == 1) exec ("mv ".ROOT_DIR."/logs/modbus.log ".ROOT_DIR."/logs/modbus.log".$i);
    elseif ($i == 7) exec ("rm ".ROOT_DIR."/logs/modbus.log".($i-1));
    else exec ("mv ".ROOT_DIR."/logs/modbus.log".($i-1)." ".ROOT_DIR."/logs/modbus.log".$i);
    $i--;
}
