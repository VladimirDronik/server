<?php

/**
 * Класс для работы с унивесальными датчиками
 */
class Usensors extends Objects
{

    /**
     * Функция запроса параметров у универсального датчика I2C
     *
     * @param int $idObject - ид объекта унивесального датчика
     */
    static function checkI2C($idObject) {

        $sql = parent::$db->query("SELECT devices.ip_address AS device, SDA, SCL FROM `usensors` INNER JOIN devices ON devices.id = usensors.id_device WHERE id_object = $idObject");
        $sensor = $sql->fetch(PDO::FETCH_OBJ);

        define("SCL", $sensor->SCL);
        define("SDA", $sensor->SDA);
        define("MD", "http://{$sensor->device}/sec/?");

        // Вариант реализации I2C: 1 - полностью программный; 2 - частично аппаратный (прошивка 3.43beta1 и выше)
        define("V", "2");

        //$temperature = get_htu21d_temperature();
        //$humidity = get_htu21d_humidity();
        $lux = get_lux();

        echo "Lux: $lux\n";

        //Добавляем данные в таблицу унивесального датчика
       // parent::$db->exec("UPDATE `usensors` SET `temp` = $temperature, `hum` = $humidity WHERE `id_object` = $idObject");

        //return ['temp'=> $temperature, 'hum'=> $humidity];
    }
}