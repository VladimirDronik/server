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
        $sql = parent::$db->query("SELECT `id_device` FROM `ports` WHERE `object` = $idObject");
        return  $sql->fetch(PDO::FETCH_OBJ)->id_device;
    }

    static public function getNumPort($idPort)
    {
        $sql = parent::$db->query("SELECT `num_port` FROM `ports` WHERE `id` = $idPort");
        return  $sql->fetch(PDO::FETCH_OBJ)->num_port;
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

        $sql = parent::$db->query("SELECT objects.id AS id, alice_devices.name AS name, objects.type AS type, 
                                      rooms.name AS room, objects.status AS status  FROM `alice_devices` 
                                      INNER JOIN objects ON alice_devices.id_object = objects.id 
                                      LEFT JOIN rooms ON alice_devices.room = rooms.id
                                      WHERE alice_devices.active = 1");

        if($sql->rowCount() > 0) {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($devices AS $device) {

                $attributes = $this->deviceAttributesToAlice($device->type, $device->status);

                $name = $device->name;
                $deviceId = $device->id;
                $type = $attributes['type'];
                $model = 'to.srv01';
                $manufacturer = 'TouchOn';
                $capabilities = $attributes['capabilities'];
                $room = $device->room;

                $devicesArr[$deviceId] = array('name' => $name, 'type' => $type, 'model' => $model,
                    'manufacturer' => $manufacturer, 'capabilities' => $capabilities, 'room' => $room);

            }

        }

        return json_encode(array('mode' => 'get_devices', 'devices' => $devicesArr));
    }


    /**
     * Формирование атрибутов для устройства Алисы в зависимости от типа
     */
    private function deviceAttributesToAlice($type, $status)
    {

        switch ($type) {


            case 'lamp':
                $type = 'devices.types.light';
                $capabilities = '[{"type":"devices.capabilities.on_off","parameters":{"instance":"'.$status.'"},"retrievable":true}]';
                break;

            case 'socket':
            case 'relay':
            case 'virtual':
                $type = 'devices.types.socket';
                $capabilities = '[{"type":"devices.capabilities.on_off","parameters":{"instance":"'.$status.'"},"retrievable":true}]';
                break;

            case 'lock':
                $type = 'devices.types.openable';
                $capabilities = '[{"type":"devices.capabilities.on_off","parameters":{"instance":"'.$status.'"},"retrievable":true}]';
                break;

            case 'dimmer':
                $type = 'devices.types.light';
                $capabilities = '[{"type":"devices.capabilities.on_off","parameters":{"instance":"on"},"retrievable":true},
                {"type":"devices.capabilities.range","parameters":{"unit":"unit.percent","range":{"min":1,"max":100,"precision":1},
                "instance":"brightness"},"retrievable":true}]';
                break;

            case 'curtain':
                $type = 'devices.types.openable.curtain';
                $capabilities = '[{"type":"devices.capabilities.on_off","parameters":{"instance":"'.$status.'"},"retrievable":true}]';
                break;
        }

        return ['type' => $type, 'capabilities' => $capabilities];

    }


    /**
     * Получение статуса устройства
     * @param $idDevice
     * @return string
     */
    public function getStatus($idDevice)
    {

        $sql = parent::$db->query("SELECT `status`, `type` FROM objects WHERE id = $idDevice");
        $device = $sql->fetch(PDO::FETCH_OBJ);

        if($device->status == 'on')
            $on = 1;
        elseif ($device->status == 'off')
            $on = 0;
        else
            $on = null;

        if (($device->type == 'lamp') || ($device->type == 'relay') || ($device->type == 'socket') )
            $status = array('on' => $on);

        if (($device->type == 'curtain'))
            $status = array('on' => $on);

        if ($device->type == 'dimmer') {
            $dimmer = new Dimmer($idDevice);
            $status = array('on' => $on, 'brightness' => $dimmer->getValue());
        }

        return json_encode(array('mode' => 'get_status', 'status' => $status));
    }
}