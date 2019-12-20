<?php

/**
 Класс работы с портами мегадевайса
 */
class Megad extends System
{

    static public $ip_device;

    /** определение ip адреса устройства */
    function ip_address($id_device)
    {
        if ($id_device == null) $ip_addr = self::$ip_device;
        else
        {
            $ip_sql = parent::$db->query("SELECT ip_address, active FROM devices WHERE id=$id_device");
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
        $device = $this->ip_address($id_device);

        //Если устройство не активно, то не выполняем действие
        if($device->active)
        file_get_contents("http://$device->ip_address/sec/?cmd=$numPort:$val");
        else
            system::addLog('device', "Сервер попытался обратиться к устройству $device->ip_address, но оно недоступно");
    }

    /**
     * Установка значения PWM на порту
     * @param int $num номер порта, на котором устанавливаем значение
     * @param int $val значение для порта
     * @param int $speed скорость изменения диммера
     */
    function setPWM($numPort, $val, $id_device, $speed=0)
    {
        $device = $this->ip_address($id_device);

        if ($speed != 0)
            $speedParam = '&cnt='.$speed;

        //Если устройство не активно, то не выполняем действие
        if($device->active)
            file_get_contents("http://$device->ip_address/sec/?pt=$numPort&pwm=$val $speedParam");
        else
            system::addLog('device', "Сервер попытался обратиться к устройству $device->ip_address, но оно недоступно");
    }

    /** Получение значения порта.
     *
     * @param int $port - номер порта меги, у которого хотим получить статус
     * @param string $command - команда на чтение параметра get, list, ...
     * @param int $id_device - ид мегадевайса из таблицы устройств
     * @param int $param - какой по счету параметр будем брать из строки, которую получим от устройства
     **/
      static function status($port, $command, $idDevice = null, $param = 0)
    {

        $state = file_get_contents("http://".self::ip_address($idDevice)->ip_address."/sec/?pt=$port&cmd=$command");
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
        file_get_contents("http://".self::ip_address($idDevice)->ip_address."/sec/?pt=$port&cnt=0");
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

        $sth = parent::$db->query("SELECT `ports`.`id`, `object`, `method`, `status`, `dc_method`, `lc_method` FROM ports 
                                  INNER JOIN devices ON ports.id_device = devices.id 
                                  WHERE devices.ip_address = '$ip_device' AND ports.num_port = $port");

        return $sth->fetch(PDO::FETCH_OBJ);
    }



}