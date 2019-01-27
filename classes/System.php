<?php

/**
* Основной класс-родитель, отвечает за базовые методы, такие как подключение к БД, авторизацию и т.д.
 * реализует так же некоторые методы для работы с системой: звуки, файлы, время и т.д.
 */


class System
{
    public static $db;


    /* Подключение к БД*/
    static function db_connect()
    {
        $db = new PDO('mysql:host=localhost;dbname=smarthome', 'smarthome', 'smartpaswd');
        Self::$db = $db;

        $db->exec("set charset utf8");
    }



    /* Проиграть звук из файла */
    function play_sound($sound)
    {
        exec("aplay /var/www/smarthome/sounds/$sound");
    }




}