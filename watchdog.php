<?php
/** Скрипт watchdog запускается по крону каждую минуту
 * и шлет серверу эхо-запрос, если данные от сервера не вернулись, то данный скрипт останавливает
 * сервекр и запускает его снова  **/

$localsocket = 'tcp://127.0.0.1:5678';

//$user = 'tester01';
$user = 'all';
$message = 'watchdog';

// connect to a local tcp-server
$instance = stream_socket_client($localsocket, $errno, $errstr, 30);

//Если сервер не откликается, то перезапускаем его
if (!$instance) {
    echo "$errstr ($errno)<br />\n";
    system("php server.php  restart");
}else
;
// send message
fwrite($instance, json_encode(['user' => $user, 'message' => $message])  . "\n");
