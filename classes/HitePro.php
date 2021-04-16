<?php

/**
 * Работа с устройствами HitePro.
 * User: kinord
 * Date: 23.08.20
 * Time: 19:20
 */
class HitePro extends System
{

    /**
     * Выполнение команды на устройстве Hite-pro
     * @param $ip_device - ip адрес устройства
     * @param $password - пароль устройства
     * @param $hpdevice - устройство, на контроллере, на которое воздействуем
     * @param $command - команда, которую передаем устройству
     */
    public static function setHiteProCommand($ip_device, $password, $hpdevice, $command, $object) {


        if ($command == 0)
            $command = 2;
        elseif ($command == 1)
            $command = 1;
        elseif($command == 2) {
            if ($object->status == 'on')
                $command = 2;
            else
                $command = 1;
        }


        $url = 'http://'.$ip_device.'/rest/devices/'.$hpdevice.'/'.$command;

        $options = [
            'http' => [
                'method'  => 'PUT',
                'header'  => [
                    'Content-type: application/json',
                    'Cookie: PHPSESSID=5e6dcf7a5adb0da0c675030edbc1e1a1',
                    'Authorization: Basic ' . $password,
                ],
            ],
        ];
        $context = stream_context_create($options);

        file_get_contents($url, false, $context);

    }

    public static function getHiteProCommand($ip_device, $password, $hpdevice, $hpDevId = null) {

        if ($hpDevId) {
            $sql = parent::$db->query("SELECT hiteprodev.id AS id, ip_address, password FROM devices 
                                    INNER JOIN hiteprodev ON devices.id = hiteprodev.id_controller 
                                    WHERE hiteprodev.id = $hpDevId");


            $device = $sql->fetch(PDO::FETCH_OBJ);

            $ip_device = $device->ip_address;
            $password =  $device->password;
            $hpdevice = $device->id;
        }

        $url = 'http://'.$ip_device.'/rest/devices/'.$hpdevice;

        $options = [
            'http' => [
                'method'  => 'GET',
                'header'  => [
                    'Content-type: application/json',
                    'Cookie: PHPSESSID=5e6dcf7a5adb0da0c675030edbc1e1a1',
                    'Authorization: Basic ' . $password,
                ],
            ],
        ];
        $context = stream_context_create($options);

        $contents = file_get_contents($url, false, $context);

        return json_decode($contents)->status;
    }



}