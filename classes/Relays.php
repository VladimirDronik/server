<?php

/**
 * Класс работы с устройствами типа "лампа"
 */
class Relays extends Device
{
    private static $idObject; // id объекта
    private static $methods;

    function __construct($idObject)
    {
        self::$idObject = $idObject;

        //Выбираем все системные методы
        $sql = parent::$db->query("SELECT id, `name` FROM methods WHERE id_object = $idObject AND is_system=1");
        if($sql->rowCount() > 0) {
            $methods = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($methods AS $method) {

                $methodsArray[$method->name] = $method->id;
            }
        }

        self::$methods = $methodsArray;
    }

    /**
     * Отдаем данные о лампах коллектору в нужном формате
     */
    public static function getToCollector() {

/*
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
*/
    }

    /**
     * Функция включения реле
     */
    public function on()
    {
        Action::runAction(self::$methods['Включить реле'], 'script', self::$idObject);
    }

    /**
     * Функция выключения реле
     */
    public function off()
    {
        Action::runAction(self::$methods['Выключить реле'], 'script', self::$idObject);
    }

    /**
     * Функция переключния реле
     */
    public function sw()
    {
        Action::runAction(self::$methods['Смена состояния реле'], 'script', self::$idObject);
    }



}