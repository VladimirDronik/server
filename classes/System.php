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
    static function dbConnect($dbname, $dbuser, $dbpass)
    {
        $db = new PDO("mysql:host=localhost;dbname=$dbname", $dbuser, $dbpass);
        Self::$db = $db;

        $db->exec("set charset utf8");
    }




    /**
     * Add new string to log-file
     *
     * @param string $typelog - тип логгирования
     * @param string $string - строка с логом
     * @return null
     */
    static function addLog($typeLog, $string, $loggingPoint = null) {

        passthru("(php -f logging.php {$typeLog} '{$string}' {$loggingPoint} &) >> /dev/null 2>&1");

        //passthru("(cd .. && php -f logging.php {$typeLog} '{$string}' {$loggingPoint} &) >> /dev/null 2>&1");

    }


    static function deleteLogs() {

        $days = self::readSetting('storage_logs');
        self::$db->query("DELETE FROM logs WHERE `date` <= (now() - INTERVAL $days DAY)");
    }

    /**
     * Функция определяет логгировать данное действие или нет
     */
    static function loggingPoint($point) {

        if($point) {
            $sql = self::$db->query("SELECT `value` FROM `logging` WHERE `point`= '$point'");
            $logging = $sql->fetch(PDO::FETCH_OBJ);
            return $logging->value;
        } else return true;


    }


    /**
     * Play a sound from a file
     *
     * @param string $sound "name of sound file"
     * @return null
     */
    function playSound($sound)
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
    static function setSetting($setting, $value)
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
    static function readSetting($setting){

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

    /**
     * Првоерка доступности устройства
     *
     * @param $host - ip адрес устройства
     * @return bool
     */
    static function ping($host) {

        exec("ping -c 5 $host",$output, $status);
        if ($status==0)
            return true;
        else
            return false;
    }

}