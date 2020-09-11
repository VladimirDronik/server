<?php

/**
 * Класс работы с устройствами типа "лампа"
 */
class Lamps extends Device
{
    private static $idObject; // id объекта диммера

    function __construct($idObject)
    {
        self::$idObject = $idObject;
    }

    /**
     * Отдаем данные о лампах коллектору в нужном формате
     */
    public static function getToCollector() {


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


            return $deviceArr;

        }

    }

    /**
     * Функция включения лампы
     */
    public function on()
    {

    }

    /**
     * Функция выключения лампы
     */
    public function off()
    {

    }

    /**
     * Функция переключния лампы
     */
    public function sw()
    {

    }



}