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

                //Если устройство недоступно
                if (!parent::ping($device->host)) {

                    //Если ранее было доступно, то изменяем состояние
                    if ($device->active == 1) {

                        //Меняем статус устройства
                        self::$db->query("UPDATE $table SET `active` = 0
                                             WHERE `id`=$device->id");

                        //Записываем в лог информацию
                        parent::addLog('error', 'Device "'.$device->description.'" ('.$device->host.') is not available', 'controller');

                        //Отправка сообщения пользователю о том, что устройство не доступно
                        Messages::send(1, 'Устройство "'.$device->description.'" ('.$device->host.') недоступно');
                    }

                } else {

                    //Если ранее было недоступно, то меняем состояние
                    if ($device->active == 0) {

                        //Меняем статус устройства
                        self::$db->query("UPDATE $table SET `active` = 1
                                         WHERE `id`=$device->id");

                        //Записываем в лог информацию
                        parent::addLog('Messages', "Device  {$device->description} ({$device->host})  is available", 'controller');

                        //Отправка сообщения пользователю о том, что устройство снова доступно
                        Messages::send(1, 'Устройство "' . $device->description . '" (' . $device->host . ') снова доступно');
                    }
                }
            }
        }
    }

    /**
     * Получение названия типа устройства
     */
    public static function getTypeName($idType)
    {

        $sql = parent::$db->query("SELECT `name` FROM devtypes WHERE `id` = $idType");
        $device = $sql->fetch(PDO::FETCH_OBJ);

        return  $device->name;

    }

    /**
     * Извлекаем из БД все данные об устройствах и отдаем их на коллектор (для Алисы)
     */
    public function getDevicesForCollector()
    {

        $sql = parent::$db->query("SELECT lamps.`name` AS `name`, lamps.`id_object` AS `id_object`, rooms.name AS `room`, 
                                    objects.status AS `status`  FROM lamps 
                                    LEFT JOIN `view_items` ON lamps.id_object = view_items.id_object 
                                    LEFT JOIN rooms ON view_items.room = rooms.id
                                    INNER JOIN objects ON lamps.`id_object` = objects.id 
                                    ");

        if($sql->rowCount() > 0) {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($devices AS $device) {

                $name = $device->name;
                $deviceId = $device->id_object;
                $type = 'devices.types.light';
                $model = 'to.light';
                $manufacturer = 'TouchOn';
                $capabilities = '[{"type":"devices.capabilities.on_off","parameters":{"instance":"'.$device->status.'"},"retrievable":true}]';
                $room = $device->room;

                $deviceArr[$deviceId] = array('name' => $name, 'type' => $type, 'model' => $model,
                'manufacturer' => $manufacturer, 'capabilities' => $capabilities, 'room' => $room);
            }


           return json_encode(array('mode' => 'get_devices', 'devices' => $deviceArr));


        }

    }


    /**
     * Получение статуса устройства
     * @param $idDevice
     * @return string
     */
    public function getStatus($idDevice)
    {

        $sql = parent::$db->query("SELECT `status` FROM objects WHERE id = $idDevice");
        $device = $sql->fetch(PDO::FETCH_OBJ);

        return json_encode(array('mode' => 'get_status', 'status' => $device->status));
    }
}