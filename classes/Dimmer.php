<?php

/**
 * Class Dimmer позволяет работать с диммируемыми портами на контроллере
 * как будто мы работаем с отдельным устройством
 */

class Dimmer extends Device
{
    private static $idObject; // id объекта диммера
    private static $speed; // скорость изменения диммера
    private static $deviceTable;

    function __construct($idObject)
    {
        self::$idObject = $idObject;
        
        $deviceTables = ['lamps', 'dimmers'];
        foreach ($deviceTables as $table)
        {
            $sql = parent::$db->query("SELECT * FROM `$table` WHERE `id_object` = $idObject");
            if ($sql->fetch(PDO::FETCH_OBJ))
            {
                self::$deviceTable = $table;
                break;
            }
        }

        $sql = parent::$db->query("SELECT `speed` FROM `".self::$deviceTable."` WHERE id_object = $idObject");
        $dimmer = $sql->fetch(PDO::FETCH_OBJ);
        self::$speed = $dimmer->speed;
    }

    /**
     * Установка скорости смены порта диммера
     * @param int @value - значение скорости, которое хотим установить
     */
    public function setSpeed($speed)
    {
        parent::$db->query("UPDATE `".self::$deviceTable."` SET `speed` = $speed WHERE id_object = ".self::$idObject);
    }

    /**
     * Установка значения яркости для диммера
     * @param int @value - значение яркости, которое хотим установить
     */
    public function setValue($value)
    {

        $object = new Objects();
        $object->select(self::$idObject);

        $mega = new Megad();

        if ($object->portstate == "OUT")
        {
            $valuePWM = round(255*$value/100);
            //Отправляем данные на порт контроллера
            $mega->setPWM($object->port, $valuePWM, $object->device, self::$speed);
        }
            elseif ($object->portstate == "0..10V")
        {
            //Отправляем данные на модуль 0-10В
            $mega->setValueToDimmerExt($object->device, $object->port, $value);
        }

        if($value > 0) {
            //Заносим текущее состояние в таблицу
            parent::$db->query("UPDATE `".self::$deviceTable."` SET `value` = $value WHERE `id_object` =".self::$idObject);
            $object->setStatus('on', true, false);
        }
        else $object->setStatus('off', true, false);     
    }

    public function setEasy($command)
    {
        $object = new Objects();
        $object->select(self::$idObject);
        $object->device;
        $object->port;

        //Отправляем данные устройству
        $mega = new Megad();
        $mega->set($object->port, $command, $object->device);

        if ($command == 'x') {

            //считываем реальное состояние порта
            $realPWM = $mega->status($object->port, 'get', $object->device);

            //Вычисляем значение в процентах и заносим в таблицу
            $value = round($realPWM*100/255);

            if($value != 0) $oldvalue = " ,`oldvalue` = $value";

            //Заносим текущее состояние в таблицу
            parent::$db->query("UPDATE `".self::$deviceTable."` SET 
                                `value` = $value $oldvalue
                                WHERE `id_object` =".self::$idObject);

            //Отображение у объекта приводим в состояние "включено" или выключено
            if ($value>0) $object->setStatus('on', true, false);
            else $object->setStatus('off', true, false);
        }
    }

    /**
     * Чтение текущего состояния яркости диммера
     */
    public function getValue()
    {
        $sql = parent::$db->query("SELECT `value` FROM `".self::$deviceTable."` WHERE `id_object` =".self::$idObject);
        $dimer = $sql->fetch(PDO::FETCH_OBJ);
        return $dimer->value;
    }

    public function getValueFromCtr() {

	$object = new Objects();
	$object->select(self::$idObject);
	$object->device;
	$object->port;

	$mega = new Megad();
        $currentValue = $mega->status($object->port, 'get', $object->device);

        //Вычисляем значение в процентах и заносим в таблицу
        $value = round($currentValue*100/255);

        if($value != 0) $oldvalue = " ,`oldvalue` = $value";
        else $oldvalue = " ,`oldvalue` = " . self::getOldValue();

        //Заносим текущее состояние в таблицу
        parent::$db->query("UPDATE `".self::$deviceTable."` SET `value` = $value $oldvalue WHERE id_object = ".self::$idObject);

        //Отображение у объекта приводим в состояние "включено" или "выключено"
        if ($value>0) $object->setStatus('on', true, false);
        else $object->setStatus('off', true, false);

    }
}
