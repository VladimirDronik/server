<?php



require_once 'webSocket.php';
require_once 'include.php';


$socket = new webSocket();

$socket->address = '0.0.0.0:8000';

//Получение списка устройсв
//$socket->getDevices();

//Получение статуса для пользователя 10, устройства 123
//$socket->getStatus(10,46);

//Установка статуса для удаленного устройства 46
$socket->setStatus(46,'on');





/*


$localsocket = 'tcp://127.0.0.1:5678';
$user = 'all';

$instance = stream_socket_client($localsocket, $errno, $errstr, 30);


if (!$instance) {
    echo "$errstr ($errno)<br />\n";
} else {
 //   fwrite($instance, "GET / HTTP/1.0\r\nHost: www.example.com\r\nAccept: \r\n\r\n");

}

$message = 'get_devices';

fwrite($instance, json_encode(['user' => $user, 'message' => $message]));
echo fgets($instance, 1024);

//while (!feof($instance)) {
  //  echo fgets($instance, 1024);
//}


fclose($instance);
*/