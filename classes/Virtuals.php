<?php

/**
 * Класс работы с виртуальными устройствами
 */
class Virtuals extends Device
{
    private static $idObject; // id объекта
    private $methodON;
    private $methodOFF;

    function __construct($idObject)
    {
        self::$idObject = $idObject;

        //находим метод вирт. устройства в таблице методов
        $sql = parent::$db->query("SELECT method_on, method_off FROM virtualobj   
                                    WHERE id_object=$idObject");
        $method = $sql->fetch(PDO::FETCH_OBJ);

        $this->methodON = $method->method_on;
        $this->methodOFF = $method->method_off;



    }

    /**
     * Отдаем данные о виртуальных устройствах коллектору в нужном формате
     */
    public static function getToCollector() {

/*
        $sql = parent::$db->query("SELECT lamps.`name` AS `name`, lamps.`id_object` AS `id_object`, rooms.name AS `room`, 
                                  //находим метод вирт. устройства в таблице методов
        $sql = parent::$db->query("SELECT method_on FROM ");    objects.status AS `status`  FROM lamps
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
     * Функция включения виртуального устройства
     */
    public function on($whence = null, $idCausing = null)
    {

        $object = new Objects();

        $object->select(self::$idObject);
        $object->setStatus('on', true, false, $whence, $idCausing);


       Action::runAction($this->methodON,'view', $idCausing);
    }

    /**
     * Функция выключения виртуального устройства
     */
    public function off($whence, $idCausing)
    {

        $object = new Objects();

        $object->select(self::$idObject);
        $object->setStatus('off', true, false, $whence, $idCausing);

        Action::runAction($this->methodOFF,'view', $idCausing);
    }

    /**
     * Функция переключния лампы
     */
    public function sw()
    {

    }



}