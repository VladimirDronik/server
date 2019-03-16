<?php
/** Скрипт watchdog запускается по крону каждую минуту
 * и шлет серверу эхо-запрос, если данные от сервера не вернулись, то данный скрипт останавливает
 * сервекр и запускает его снова  **/

require_once __DIR__ . '/include.php';

$localsocket = 'tcp://127.0.0.1:5678';

$user = 'watchdog';
$message = 'watchdog';

//Флаг рестарта
$restart = false;

// connect to a local tcp-server
$instance = stream_socket_client($localsocket, $errno, $errstr, 30);

//Если сервер не откликается, то перезапускаем его
if (!$instance) {
   $restart = true;
}else {

    //Шлем тестовое сообщеение через сокет
    fwrite($instance, json_encode(['user' => $user, 'message' => $message])  . "\n");

    sleep(5);

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

    unlink('watchdog.txt');

}

if ($restart) {
    System::addlog('Server is restarted from the watchdog');
    system("php server.php restart");
}


