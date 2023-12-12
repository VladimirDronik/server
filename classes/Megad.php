<?php

/**
 * Класс работы с портами мегадевайса
 */
class Megad extends System
{

    static public $ip_device;

    /** определение ip адреса устройства */
    static function getDeviceParams($id_device)
    {
        if ($id_device == null) $ip_addr = self::$ip_device;
        else
        {
            $ip_sql = parent::$db->query("SELECT `ip_address`, `type`, `active`, `password`
                                              FROM devices WHERE id=$id_device");
            $device = $ip_sql->fetch(PDO::FETCH_OBJ);
        }

        return $device;

    }


    /**
     * Установка значения порта для устройства.
     * @param int $num номер порта, на котором устанавливаем значение
     * @param int $val значение для порта
     */
    function set($numPort, $val, $id_device=null)
    {
        $device = $this->getDeviceParams($id_device);
        if (empty($device->password)) $devicePassword = "";
        else $devicePassword = $device->password . "/";

        //Если устройство не активно, то не выполняем действие
        if($device->active)
            file_get_contents("http://$device->ip_address/$devicePassword?cmd=$numPort:$val");
        else
            system::addLog('error', "Сервер попытался обратиться к устройству $device->ip_address, но оно недоступно", 'controller');

        //Получаем состояние порта, на который воздействуем
        $state = file_get_contents("http://$device->ip_address/$devicePassword?pt=$numPort&cmd=get");
        $state = mb_strtolower(explode('/', $state)[0]);

        return $state;

    }

    /**
     * Установка значения PWM на порту
     * @param int $num номер порта, на котором устанавливаем значение
     * @param int $val значение для порта
     * @param int $speed скорость изменения диммера
     */
    function setPWM($numPort, $val, $id_device, $speed=0)
    {
        $device = $this->getDeviceParams($id_device);

        if ($speed != 0)
            $speedParam = '&cnt='.$speed;

        //Если устройство не активно, то не выполняем действие
        if($device->active)
            file_get_contents("http://$device->ip_address/sec/?pt=$numPort&pwm={$val}{$speedParam}");
        else
            system::addLog('error', "Сервер попытался обратиться к устройству $device->ip_address, но оно недоступно", 'controller');
    }

    /** Получение значения порта.
     *
     * @param int $port - номер порта меги, у которого хотим получить статус
     * @param string $command - команда на чтение параметра get, list, ...
     * @param int $id_device - ид мегадевайса из таблицы устройств
     * @param int $param - какой по счету параметр будем брать из строки, которую получим от устройства
     **/
      static function status($port, $command = 'get', $idDevice = null, $param = 0)
    {
        $state = file_get_contents("http://".self::getDeviceParams($idDevice)->ip_address."/sec/?pt=$port&cmd=$command");
        $state = explode('/',$state);
        return $state[$param];
    }

    /**
     * Обнуление счетчика порта
     *
     * @param int $idDevice - id устройства
     * @param int $port - порт устройства на который воздействуем
     */
    static function resetCount($idDevice, $port)
    {
        file_get_contents("http://".self::getDeviceParams($idDevice)->ip_address."/sec/?pt=$port&cnt=0");
    }



    /**
     * Получение данных из таблицы пртов для порта, который активировали на девайсе
     *
     * @param int $port - физический порт устройства, который сработал
     * @return object
    */
    function get($port)
    {
        $ip_device = self::$ip_device;

        $sth = parent::$db->query("SELECT `ports`.`id`, `object`, `status`,
                                          `method`, `dc_method`, `lc_method`
                                   FROM ports 
                                   INNER JOIN devices ON ports.id_device = devices.id 
                                   WHERE devices.ip_address = '$ip_device' AND ports.num_port = $port");

        return $sth->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Получение данных с датчика I2C
     *
     * @param int $idDevice - ид контроллера
     * @param int $SDA - порт SDA на котором висит датчик
     * @param int $SCL - порт SCL на котором висит датчик
     * @param string $sensorType - тип датчика
     * @param string $i2cParametr - парметры, которые передаем датчику
     *
     * @return string $value - значение датчика
     */
    static function getI2C($idDevice, $SDA, $SCL, $sensorType, $i2cParametr=null)
    {
        $device = self::getDeviceParams($idDevice);
        
        if($device->active)
        {
            $get_str = "http://$device->ip_address/sec/?pt=$SDA&scl=$SCL&i2c_dev=$sensorType";
            if ($i2cParametr) $get_str .= "&i2c_par=$i2cParametr";
            $value = file_get_contents($get_str);
            return $value;
        }
        
    }

    //Установка значения через расширитель портов
    function setValueToDimmerExt($idDevice, $sdaPort, $numPort, $value) {

        $device = self::getDeviceParams($idDevice);

        $value = round(4095*$value/100);

        if($device->active)
            file_get_contents("http://$device->ip_address/sec/?cmd=".$sdaPort."e".$numPort.":".$value);


    }

}