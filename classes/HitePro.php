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
    public static function setHiteProCommand($ip_device, $password, $hpdevice, $command) {


        if ($command == 0)
            $command = 2;
        elseif ($command == 1)
            $command = 1;

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

        $contents = file_get_contents($url, false, $context);

        return json_decode($contents);
    }

    public static function getHiteProCommand($ip_device, $password, $hpdevice) {

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

        return json_decode($contents);
    }

}