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
                                    portsSDA.num_port AS SDA
                                    FROM `usensors` 
                                    INNER JOIN devices ON devices.id = usensors.device_id 
                                    INNER JOIN ports AS portsSCL ON portsSCL.id = usensors.port_SCL 
                                    INNER JOIN ports AS portsSDA ON portsSDA.id = usensors.port_SDA 
                                    WHERE id_object = $idObject");

        $sensor = $sql->fetch(PDO::FETCH_OBJ);

        $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d');
        if (preg_match("/[a-z]/i", $humidity)) $humidity = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280', 2);
        $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'htu21d', 1);
        if (preg_match("/[a-z]/i", $temperature)) $temperature = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280', 1);
        $lux = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bh1750');         
        $atm_pressure = Megad::getI2C($sensor->device_id, $sensor->SDA, $sensor->SCL, 'bmx280');

        //Проверка на наличие значения
        if(preg_match("/[a-z]/i", $temperature)) $temperature='NULL';
        if(preg_match("/[a-z]/i", $humidity)) $humidity='NULL';
        if(preg_match("/[a-z]/i", $lux)) $lux='NULL';
        if(preg_match("/[a-z]/i", $atm_pressure)) $atm_pressure='NULL';
        
        //Добавляем данные в таблицу унивесального датчика
        if( $result = parent::$db->query("SELECT * FROM usensors")  &&  isset($result['atm_pressure']) ){
            parent::$db->exec("UPDATE `usensors` SET
                            `temp` = $temperature,
                            `hum` = $humidity,
                            `lux` = $lux,
                            `atm_pressure` = $atm_pressure
                            WHERE `id_object` = $idObject");
        }else{
            parent::$db->exec("UPDATE `usensors` SET
                            `temp` = $temperature,
                            `hum` = $humidity,
                            `lux` = $lux
                            WHERE `id_object` = $idObject");
        }

        return ['temp' => $temperature, 'hum' => $humidity, 'lux' => $lux, 'atm_pressure' => $atm_pressure];
    }
}