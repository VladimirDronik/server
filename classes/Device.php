<?php

/**
 * Class Device - класс устройств
 */


class Device extends System
{


    /**
     * Определение на каком контроллере находится устройство
     *
     * */
    static public function getDevice($idObject)
    {

    }

    /**
     * Определение типа порта для устройств i2c
     */
    static public function getPortType($idObject)
    {

    }

    /**
     * Проверка устройства на доступность
     *
     * @param $table
     */
    static public function checkAvailible($table)
    {

        $sql = parent::$db->query("SELECT id, 
                                       ip_address AS host,
                                       description,
                                       active
                                       FROM $table
                                  ");

        if($sql->rowCount() > 0) {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($devices AS $device) {

                if (!parent::ping($device->host)) {

                    //Меняем статус устройства
                    self::$db->query("UPDATE $table SET `active` = 0
                                         WHERE `id`=$device->id");

                    //Записываем в лог информацию
                    parent::addLog('error', 'Device "'.$device->description.'" ('.$device->host.') is not available', 'controller');

                    //Отправка сообщения пользователю о том, что устройство не доступно
                    Messages::send(1, 'Устройство '.$device->description.'" ('.$device->host.') недоступно');

                } elseif (!$device->active) {  //Если устройство было не активно

                    //Меняем статус устройства
                    self::$db->query("UPDATE $table SET `active` = 1
                                         WHERE `id`=$device->id");

                    //Записываем в лог информацию
                    parent::addLog('Messages', "Device  {$device->description} ({$device->host})  is available", 'controller');

                    //Отправка сообщения пользователю о том, что устройство снова доступно
                    Messages::send(1, 'Устройство '.$device->description.'" ('.$device->host.') снова доступно');
                }

            }
        }
    }
}