<?php

/**
 * Класс для работы с унивесальными датчиками
 */
class Usensors extends Objects
{

    /**
     * Функция запроса параметров у датчиков I2C
     *
     * @param int $idObject - ид объекта датчика I2C
     */
    
    static function checkI2C($idObject)
    {
        $sql = parent::$db->query("SELECT devices.ip_address AS device_ip, 
                                    devices.type AS device_type,
                                    devices.id AS device_id,
                                    portsSCL.num_port AS SCL,
                                    portsSDA.num_port AS SDA,
                                    usensors.type AS sensor_type
                                    FROM `usensors` 
                                    INNER JOIN devices ON devices.id = usensors.device_id 
                                    INNER JOIN ports AS portsSCL ON portsSCL.id = usensors.port_SCL 
                                    INNER JOIN ports AS portsSDA ON portsSDA.id = usensors.port_SDA 
                                    WHERE id_object = $idObject");

        $sensor = $sql->fetch(PDO::FETCH_OBJ);

        switch ($sensor->sensor_type)
        {
            case 'bh1750':
                $lux = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bh1750');
                break;

            case 'htu21d':
                $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d');
                $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d', 1);
                break;

            case 'bme280':
                $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280', 2);
                $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280', 1);
                $atm_pressure = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280');
                break;

            case 'outdoorv2':
                $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d');
                $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d', 1);
                $lux = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bh1750');
                break;

            case 'outdoorv3':
                $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280', 2);
                $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280', 1);
                $atm_pressure = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280');
                $lux = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bh1750');
                break;

            case 'scd40':
            case 'scd41':
                $co2 = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'scd4x', 1);
                $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'scd4x', 2);
                $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'scd4x', 3);
                break;

            case 'ptsensor':
                // Отправка запроса на измерение давления 
                Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'ptsensor', 1);
                sleep (1);
                // Получение данных
                $pressure = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'ptsensor', 2);
        }

        //Проверка на наличие значения
        if (!isset($temperature)) $temperature='NULL';
        if(preg_match("/[a-z]/i", $temperature)) $temperature='NULL';
        if (!isset($humidity)) $humidity='NULL';
        if(preg_match("/[a-z]/i", $humidity)) $humidity='NULL';
        if (!isset($lux)) $lux='NULL';
        if(preg_match("/[a-z]/i", $lux)) $lux='NULL';
        if (!isset($atm_pressure)) $atm_pressure='NULL';
        if(preg_match("/[a-z]/i", $atm_pressure)) $atm_pressure='NULL';
        if (!isset($pressure)) $pressure='NULL';
        if(preg_match("/[a-z]/i", $pressure)) $pressure='NULL';
        if (!isset($co2)) $co2='NULL';
        if(preg_match("/[a-z]/i", $co2)) $co2='NULL';
        
        //Добавляем данные в таблицу унивесального датчика
        parent::$db->exec("UPDATE `usensors` 
                           SET `temp` = $temperature,
                               `hum` = $humidity,
                               `lux` = $lux,
                               `atm_pressure` = $atm_pressure,
                               `pressure` = $pressure,
                               `co2` = $co2
                           WHERE `id_object` = $idObject");

        return ['temp' => $temperature, 'hum' => $humidity, 'lux' => $lux, 'atm_pressure' => $atm_pressure, 'pressure' => $pressure, 'co2' => $co2];
    }
}
