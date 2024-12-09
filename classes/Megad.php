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
                                            FROM `devices`
                                           WHERE `id` = $id_device");
            $device = $ip_sql->fetch(PDO::FETCH_OBJ);
            if (empty($device->password)) $device->password = "";
            else $device->password = $device->password . "/";
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
        $device = self::getDeviceParams($id_device);

        //Если устройство не активно, то не выполняем действие
        if($device->active)
            file_get_contents("http://$device->ip_address/$device->password?cmd=$numPort:$val");
        else
            system::addLog('error', "Сервер попытался обратиться к устройству $device->ip_address, но оно недоступно", 'controller');

        //Получаем состояние порта, на который воздействуем
        $state = file_get_contents("http://$device->ip_address/$device->password?pt=$numPort&cmd=get");
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
        $device = self::getDeviceParams($id_device);

        if ($speed != 0) $speedParam = '&cnt='.$speed;
        else $speedParam = '';
        
        //Если устройство не активно, то не выполняем действие
        if($device->active)
            file_get_contents("http://$device->ip_address/$device->password?pt=$numPort&pwm=$val$speedParam");
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
        $device = self::getDeviceParams($idDevice);
        $state = file_get_contents("http://".self::getDeviceParams($idDevice)->ip_address."/$device->password?pt=$port&cmd=$command");
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
        $device = self::getDeviceParams($idDevice);
        file_get_contents("http://".self::getDeviceParams($idDevice)->ip_address."/$device->password?pt=$port&cnt=0");
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
        $sth = parent::$db->query("SELECT `ports`.`id`, `object`, `status`, `method`, `dc_method`, `lc_method`
                                     FROM `ports` 
                               INNER JOIN `devices` ON `ports`.`id_device` = `devices`.`id` 
                                    WHERE `devices`.`ip_address` = '$ip_device' AND `ports`.`num_port` = $port");
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
    static function getI2C($idDevice, $SDA, $SCL, $sensorType, $i2cParametr = null, $devAndParam = null)
    {
        $device = self::getDeviceParams($idDevice);
        
        if($device->active)
        {
            $get_str = "http://$device->ip_address/$device->password?pt=$SDA&scl=$SCL&";
            if (isset($sensorType)) $get_str .= "i2c_dev=$sensorType";
            if (isset($i2cParametr)) $get_str .= "&i2c_par=$i2cParametr";
            $value = file_get_contents($get_str);
            return $value;
        }
        
    }

    //Установка значения через расширитель портов
    function setValueToDimmerExt($idDevice, $numPort, $value) {

        $device = self::getDeviceParams($idDevice);

        $value = round(4095*$value/100);

        if($device->active)
            file_get_contents("http://$device->ip_address/$device->password?cmd=".$numPort.":".$value);

    }

    /**
     * Актуализация статусов OUT портов со статусами объектов из БД
     */
    public function restoreOutPortsStatus()
    {
        $ipAddress = self::$ip_device;
        $object = new Objects();
        $objectsWithStatus = [];

        $sql = System::$db->query(" SELECT `ports`.`object`, `ports`.`status`, `ports`.`num_port`, `objects`.`status`
                                    FROM `ports` 
                                    INNER JOIN `devices` ON `devices`.`id`=`ports`.`id_device`
                                    INNER JOIN `objects` ON `objects`.`id` = `ports`.`object`
                                    WHERE `ports`.`object` IS NOT NULL
                                    AND `devices`.`ip_address` = '$ipAddress'
                                    AND LOWER(`ports`.`status`) = 'out'");

        while ($outPortObject = $sql->fetch(PDO::FETCH_OBJ))
        {
            $objectsWithStatus [$outPortObject->object] = $outPortObject->status;
        }

        foreach ($objectsWithStatus as $objectId => $objectStatus)
        {
            $object->select($objectId);
            $object->setStatus($objectStatus, true, true);
        }
    }

    public static function getPortValue($idDevice, string $getQueryParams)
    {
        $device = self::getDeviceParams($idDevice);
        
        if($device->active)
        {
            $get_str = "http://$device->ip_address/$device->password?" . $getQueryParams;

            $value = file_get_contents($get_str);
            return $value;
        }
    }

    public static function getPortNum($portId) {
        return parent::$db->query("SELECT `num_port` FROM `ports` WHERE `id` = $portId")
            ->fetchColumn();
    }
}