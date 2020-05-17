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

        echo "SELECT devices.ip_address AS device_ip, devices.type AS device_type, 
                                   devices.id AS device_id , portsSCL.num_port AS SCL , portsSDA.num_port AS SDA 
                                   FROM `usensors` 
                                   INNER JOIN devices ON devices.id = usensors.device_id 
                                   INNER JOIN ports AS portsSCL ON portsSCL.id = usensors.port_SCL 
                                   INNER JOIN ports AS portsSDA ON portsSDA.id = usensors.port_SDA 
                                   WHERE id_object = $idObject";

        $sql = parent::$db->query("SELECT devices.ip_address AS device_ip, devices.type AS device_type, 
                                   devices.id AS device_id , portsSCL.num_port AS SCL , portsSDA.num_port AS SDA 
                                   FROM `usensors` 
                                   INNER JOIN devices ON devices.id = usensors.device_id 
                                   INNER JOIN ports AS portsSCL ON portsSCL.id = usensors.port_SCL 
                                   INNER JOIN ports AS portsSDA ON portsSDA.id = usensors.port_SDA 
                                   WHERE id_object = $idObject");

        $sensor = $sql->fetch(PDO::FETCH_OBJ);

        //Для мегадевейса используем аппаратный способ чтения для датчиков  HTU21D и MAX44009
        if ($sensor->device_type == 1) {

            $cnt = 0;

            do {
                $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d', 0);
                $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d', 1);
                $lux = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'max44009', 0);

            $cnt++;
            if ($cnt>5) break;

            } while ($humidity == 'NA' AND $temperature == 'NA' AND $lux == 'NA');

        } else {
            //Для нашего контроллера используем программный способ чтения для датчиков  HTU21D и BH1750

            define("SCL", $sensor->SCL);
            define("SDA", $sensor->SDA);
            define("MD", "http://{$sensor->device_ip}/sec/?");

            // Вариант реализации I2C: 1 - полностью программный; 2 - частично аппаратный (прошивка 3.43beta1 и выше)
            define("V", "2");


            $temperature = get_htu21d_temperature();
            $humidity = get_htu21d_humidity();
            $lux = get_lux1750();
        }

        //Добавляем данные в таблицу унивесального датчика
        parent::$db->exec("UPDATE `usensors` SET `temp` = $temperature, `hum` = $humidity, `lux` = $lux WHERE `id_object` = $idObject");

        return ['temp' => $temperature, 'hum' => $humidity, 'lux' => $lux];
    }
}