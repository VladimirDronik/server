<?php
/**
 * Скрипт удаляет старые логи. Запускается раз в день по расписанию.
 */

require_once '../include.php';

System::deleteLogs();
exec ("rm ".ROOT_DIR."/logs/modbus.log");