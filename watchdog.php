<?php
/** Скрипт watchdog запускается по крону каждую минуту
 * и шлет серверу эхо-запрос, если данные от сервера не вернулись, то данный скрипт останавливает
 * сервекр и запускает его снова  **/

require_once __DIR__ . '/include.php';

$user = 'watchdog';
$message = 'watchdog';

//Флаг рестарта
$restart = false;

// connect to a local tcp-server
$instance = stream_socket_client($localsocket, $errno, $errstr, 30);

//Если сервер не откликается, то перезапускаем его
if (!$instance) {
   $restart = true;
}

else {

    //Читаем файл первый раз на предмет ошибок
    $handle = @fopen("watchdog.txt", "r");
    if ($handle) {
        while (($buffer = fgets($handle, 4096)) !== false) {
            if ($buffer != 'OK') $restart = true;
        }
        if (!feof($handle)) {
            $restart = true;
        }
        fclose($handle);
    }


    //Шлем тестовое сообщеение через сокет
    fwrite($instance, json_encode(['user' => $user, 'message' => $message])  . "\n");

    sleep(1);

    //Читаем строку из файла
    $handle = @fopen("watchdog.txt", "r");
    if ($handle) {
        while (($buffer = fgets($handle, 4096)) !== false) {
            if ($buffer != 'OK') $restart = true;
        }
        if (!feof($handle)) {
            $restart = true;
        }
        fclose($handle);
    }

    if (file_exists('watchdog.txt'))
    unlink('watchdog.txt');
}

if ($restart) {
    System::addLog('socket', 'Server is restarted from the watchdog');
    system("php server.php restart");
}

return true;


