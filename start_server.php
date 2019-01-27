<?php
/** Скрипт запускает сервер сокетов при загрузке системы с задержкой 1 минута
 *   Добавлен в крон строкой вида
 *   @reboot cd /var/www/smarthome && php start_server.php
 */

sleep(60);
exec("php server.php start  > /tmp/socket_server.log 2>&1");


?>
