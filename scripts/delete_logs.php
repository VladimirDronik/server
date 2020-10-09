<?php
/**
 * Скрипт удаляет старые логи. Запускается раз в день по расписанию.
 */

require_once '../include.php';

System::deleteLogs();