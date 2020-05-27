<?php
/** Тестовый  скрипт для проверки работоспособности отправки данных клиенту **/
$localsocket = 'tcp://127.0.0.1:5678';

//$user = 'tester01';
$user = 'all';
$message = '{ "status": "itemChange", "items": [{"id":2,"name":"light-own","status":"off","left":"290","top":"155"}]}';
//$message = '{"status":"item_Change","items":{"id":"2","name":"light-own","status":"OFF","left":null,"top":null}}';

// connect to a local tcp-server
$instance = stream_socket_client($localsocket);
// send message
fwrite($instance, json_encode(['user' => $user, 'Messages' => $message])  . "\n");


