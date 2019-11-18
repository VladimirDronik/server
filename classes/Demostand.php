<?php

/**
Класс для работы с отображением информации на демостенде. В работе на реальном устройстве не используется
 */
class Demostand
{

    private static $socketserver = 'localhost:5678';
    private static $user = 'demostand';

    //Отправка данных серверу соккетов
    static function send($message){

        $instance = stream_socket_client(self::$socketserver);
        fwrite($instance, json_encode(['user' => self::$user, 'message' => $message])  . "\n");

    }

}