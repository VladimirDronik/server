<?php

include 'libs/YandexTTS.php';


// Путь к файлу с куки.
$pathToCookieFile = 'cookie.txt';

/**
 *    Первичная инициализация. 
 *    Запускается однократно, а также после добавления/удаления колонок в яндекс-аккаунт.
 */

$tts = new YandexTTS($pathToCookieFile, true);

if($tts->init() === true) {
   echo 'Инициализация Yandex TTS выполнена успешно.' . PHP_EOL;
} else {
   echo 'В процессе инициализации Yandex TTS возникли проблемы.' . PHP_EOL;
}
