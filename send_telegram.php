<?php
/**
 * Отправка сообщений телеграм боту
 */

$gram=json_decode(file_get_contents('php://input'),true);

$chatId = $argv[1];
$message= $argv[2];


switch ($argv[3]) {

    case 1:
        $priority = 'E280BC';
        break;

    case 2:
        $priority = 'F09F92AC';
        break;

    default:
        $priority = '';
}

file_get_contents('https://api.telegram.org/bot1232846380:AAE7_ypFvBjlF0yn7RZADQkYI7bcRFuu508/sendMessage?chat_id='.$chatId.'&text='
    .urlencode(hex2bin($priority).'  '.$message));