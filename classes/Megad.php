<?php

/**
 Класс работы с портами мегадевайса
 */
class Megad extends System
{

    static public $ip_device;

    /** определение ip адреса устройства */
    private function ip_address($id_device)
    {
        if ($id_device == null) $ip_addr = self::$ip_device;
        else
        {
            $ip_sql = parent::$db->query("SELECT ip_address, active FROM devices WHERE id=$id_device");
            $device = $ip_sql->fetch(PDO::FETCH_OBJ);
        }

        return $device;

    }


    /** Установка значения порта. На входе номер порта $num и значение, которое устанавливаем $val */
    function set($num, $val, $id_device=null)
    {
        $device = $this->ip_address($id_device);

        //Если ip адрес равен 0, то не выполняем действие
        if($device->active)
        file_get_contents("http://$device->ip_address/sec/?cmd=$num:$val");
        else
            system::addlog("Сервер попытался обратиться к устройству $device->ip_address, но оно недоступно");
    }


    /** Получение значения порта. На входе номер порта $port и возможно $id_device*/
      static function status($port, $command, $id_device=null)
    {

        $state = file_get_contents("http://".self::ip_address($id_device)->ip_address."/sec/?pt=$port&cmd=$command");
        $state = explode('/',$state);
        return $state[0];
    }



    /** Получение номера порта, который активировал девайс*/
    function get(int $port)
    {
        $ip_device = self::$ip_device;

        $sth = parent::$db->query("SELECT `easy`, `object`, `method`, `script`, `status` FROM ports 
                                  INNER JOIN devices ON ports.id_device = devices.id 
                                  WHERE devices.ip_address = '$ip_device' AND ports.num_port = $port");

        return $sth->fetch(PDO::FETCH_OBJ);
    }


}