<?php

/**
 * Rest API realisation
 * */

require_once __DIR__ . '/include.php';

//Проверяем доступность сервера и перезапускаем, если необходимо
$success = include('watchdog.php');

//Проверяем связку логин-пароль

$sql = system::$db->query("SELECT password FROM users WHERE `name` = '{$_POST['login']}'");
$user = $sql->fetch(PDO::FETCH_OBJ);


if (password_verify( $_POST['password'], $user->password))
    $login=true;
else
    $login=false;


//Если всё ок, то отправляем данные
if ($success && $login)
    $data = array ('status' => 'success', 'server' => $websocket, 'login'=>$login);
else
    $data = array('status' => 'fall', 'server' => $success, 'login'=>$login);

header("Access-Control-Allow-Origin: *");
header('HTTP/1.1 200 OK; Content-Type: application/json');
print_r( json_encode($data));

