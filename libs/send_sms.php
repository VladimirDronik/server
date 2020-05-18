<?php
/**
 * Отправка смс пользователю
 */

$phone = $argv[1];
$message = $argv[2];

file_get_contents("http://sms.ru/sms/send?api_id=d35b5271-263c-c014-d128-5dfde9702997&to=$phone&text=$message");