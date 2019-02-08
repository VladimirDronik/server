<?php

/**
* Основной класс-родитель, отвечает за базовые методы, такие как подключение к БД, авторизацию и т.д.
 * реализует так же некоторые методы для работы с системой: звуки, файлы, время и т.д.
 */


class System
{
    public static $db;

    /* Подключение к БД*/
    static function db_connect($dbname, $dbuser, $dbpass)
    {
        $db = new PDO("mysql:host=localhost;dbname=$dbname", $dbuser, $dbpass);
        Self::$db = $db;

        $db->exec("set charset utf8");
    }

    /* Добавляет новую строку в лог файл*/
    static function addlog($string){

        $date = date('m/d/Y H:i:s', time());
        $file = '../server.log';
        file_put_contents($file, $date.':   '.$string."\n", FILE_APPEND | LOCK_EX);
    }


    /* Проиграть звук из файла */
    function play_sound($sound)
    {
        exec("aplay /var/www/smarthome/sounds/$sound");
    }




}