<?php

include 'YandexTTS.php';

// Путь к файлу с куки.
$pathToCookieFile = 'cookie.txt';

/**
 *    Типовое использование.
 */

$tts = new YandexTTS($pathToCookieFile, true);

// Идентификатор колонки, на которую отправляем команду.
$speaker_id = '8e7906a0-9a20-4e4a-9271-bfe93310d0d1';

// Произнести текст.
$tts->say('Привет! Температура воздуха в гостиной 22 градуса.', $speaker_id);

sleep(3);

// Выполнить как команду.
$tts->cmd('Какая завтра погода?', $speaker_id);
//$tts->cmd('Включи мою музыку вперемешку', $speaker_id);
