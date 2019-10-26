<?php

/**
* Основной класс-родитель, отвечает за базовые методы, такие как подключение к БД, авторизацию и т.д.
 * реализует так же некоторые методы для работы с системой: звуки, файлы, время и т.д.
 */


class System
{
    /**
     * Connection to database
     * @var object
     */
    public static $db;




    /**
     * Подключение к БД
     *
     * @param string $dbname "name of database"
     * @param string $dbuser "user of database"
     * @param string $dbpass "password of database"
     * @return  null
     */
    static function db_connect($dbname, $dbuser, $dbpass)
    {
        $db = new PDO("mysql:host=localhost;dbname=$dbname", $dbuser, $dbpass);
        Self::$db = $db;

        $db->exec("set charset utf8");
    }




    /**
     * Add new string to log-file
     *
     * @param string $string
     * @return null
     */
    static function addlog($string){
        $date = date('m/d/Y H:i:s', time());
        $file = ROOT_DIR.'/server.log';
        file_put_contents($file, $date.':   '.$string."\n", FILE_APPEND | LOCK_EX);
    }




    /**
     * Play a sound from a file
     *
     * @param string $sound "name of sound file"
     * @return null
     */
    function play_sound($sound)
    {
        exec("aplay /var/www/smarthome/sounds/$sound");
    }


    /**
     * Update a setting
     *
     * @param string $setting "setting name for update a value"
     * @param string $value "value setting to update"
     * @return null
     */
    static function set_setting($setting, $value)
    {

        self::$db->query("UPDATE settings SET `value` = '$value'
                                         WHERE `name`='$setting'");
    }

    /**
     * Чтение данных из таблицы свойств
     *
     * @param string $setting "Название свойства"
     * @return strings
    */
    static function read_setting($setting){

        $sql = self::$db->query("SELECT `value` FROM `settings` WHERE `name`= '$setting'");
        $setting = $sql->fetch(PDO::FETCH_OBJ);
        return $id_object = $setting->value;

    }

    /**
     * Проверка подключения к БД (просто эмулируем активность клиента веб сокетов для того, что бы
     * взбодрить СУБД)
     */

    static function checkConnection(){

        $sql = self::$db->query("SELECT `id` FROM `settings` LIMIT 1 ");
        $setting = $sql->fetch(PDO::FETCH_OBJ);
    }


}