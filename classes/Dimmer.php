<?php

/**
 * Class Dimmer позволяет работать с диммируемыми портами на контроллере
 * как будто мы работаем с отдельным устройством
 */

class Dimmer extends Device
{
    private static $idObject; // id объекта диммера
    private static $speed; // скорость изменения диммера


    function __construct($idObject)
    {
        self::$idObject = $idObject;

        $sql = parent::$db->query("SELECT `speed` FROM `dimmers` WHERE id_object = $idObject");
        $dimmer = $sql->fetch(PDO::FETCH_OBJ);
        self::$speed = $dimmer->speed;
    }

    /**
     * Установка скорости смены порта диммера
     * @param int @value - значение скорости, которое хотим установить
     */
    public function setSpeed($value)
    {
        parent::$db->query("UPDATE dimmers SET 
                                `speed` = $value
                                WHERE id_object = self::$idObject");
    }

    /**
     * Установка значения яркости для диммера
     * @param int @value - значение яркости, которое хотим установить
     */
    public function setValue($value)
    {
        $object = new Objects();
        $object->select(self::$idObject);
        $object->device;
        $object->port;

        $value = round(255*$value/100);

        //Отправляем данные устройству
        $mega = new Megad();
        $mega->setPWM($object->port, $value, $object->device, self::$speed);

        //Заносим текущее состояние в таблицу
    }

    /**
     * Чтение текущего состояния яркости диммера
     */
    public function getValue()
    {

    }

}