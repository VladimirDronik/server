<?php

/**
 * Class Dimmer позволяет работать с диммируемыми портами на контроллере
 * как будто мы работаем с отдельным устройством
 */

class Dimmer extends Device
{
    private static $idObject;

    function __construct($idObject)
    {
        self::$idObject = $idObject;
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
        //Определяем id устройства
        $mega = new Megad();
//        $mega->ip_address()
    }

    /**
     * Чтение текущего состояния яркости диммера
     */
    public function getValue()
    {

    }

}