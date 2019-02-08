<?php

/**
 Класс работы с портами мегадевайса
 */
class Megad extends System
{

    static public $ip_device;

    /** определение ip адреса устройства */
    private function ip_address(int $id_device)
    {
        if ($id_device == null) $ip_addr = self::$ip_device;
        else
        {
            $ip_sql = parent::$db->query("SELECT ip_address FROM devices WHERE id=$id_device");
            $ip_addr = $ip_sql->fetch(PDO::FETCH_OBJ)->ip_address;
        }

        return $ip_addr;

    }


    /** Установка значения порта. На входе номер порта $num и значение, которое устанавливаем $val */
    function set($num, $val, $id_device=null)
    {
        $ip = $this->ip_address($id_device);
        file_get_contents("http://$ip/sec/?cmd=$num:$val");
    }


    /** Получение значения порта. На входе номер порта $port и возможно $id_device*/
      static function status($port, $command, $id_device=null)
    {
        $state = file_get_contents("http://".self::ip_address($id_device)."/sec/?pt=$port&cmd=$command");
        $state = explode('/',$state);
        return $state[0];
    }



    /** Получение номера порта, который активировал девайс*/
    function get(int $port)
    {
       $ip_addr = self::$ip_device;

        $sth = parent::$db->query("SELECT `easy`, `object`, `method`, `script` FROM ports 
                                  INNER JOIN devices ON ports.id_device = devices.id 
                                  WHERE devices.ip_address = '$ip_addr' AND ports.num_port = $port");

        return $sth->fetch(PDO::FETCH_OBJ);
    }


}