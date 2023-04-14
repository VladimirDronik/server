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
        $dimer = $sql->fetch(PDO::FETCH_OBJ);
        self::$speed = $dimer->speed;
    }

    /**
     * Установка скорости смены порта диммера
     * @param int @value - значение скорости, которое хотим установить
     */
    public function setSpeed($speed)
    {
        parent::$db->query("UPDATE dimmers SET 
                                `speed` = $speed
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

        $mega = new Megad();

        if ($object->portstate == "OUT")
        {
            $valuePWM = round(255*$value/100);
            //Отправляем данные на порт контроллера
            $mega->setPWM($object->port, $valuePWM, $object->device, self::$speed);
        }
            elseif ($object->portstate == "EXT")
        {
            //Отправляем данные на модуль 0-10В
            $mega->setValueToDimmerExt($object->device, $object->port, $value);
        }

        if($value != 0) $oldvalue = " ,`oldvalue` = $value";

        //Заносим текущее состояние в таблицу
            parent::$db->query("UPDATE dimmers SET
                                `value` = $value $oldvalue
                                WHERE id_object =".self::$idObject);
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
            parent::$db->query("UPDATE dimmers SET 
                                `value` = $value $oldvalue
                                WHERE id_object =".self::$idObject);

            //Отображение у объекта приводим в состояние "включено" или выключено
            if ($value>0)
            $object->setStatus('ON');
            else
                $object->setStatus('OFF');
        }
    }

    /**
     * Чтение текущего состояния яркости диммера
     */
    public function getValue()
    {
        $sql = parent::$db->query('SELECT `value` FROM `dimmers` WHERE id_object ='.self::$idObject);
        $dimer = $sql->fetch(PDO::FETCH_OBJ);
        return $dimer->value;
    }

    /**
     * Получение значения предыдущего состояния диммера
     * @return mixed
     */
    public function getOldValue()
    {
        $sql = parent::$db->query('SELECT `oldvalue` FROM `dimmers` WHERE id_object ='.self::$idObject);
        $dimer = $sql->fetch(PDO::FETCH_OBJ);
        return $dimer->oldvalue;
    }


    public function getValueFromCtr()
    {

	$object = new Objects();
	$object->select(self::$idObject);
	$object->device;
	$object->port;

	$mega = new Megad();
        $currentValue = $mega->status($object->port, 'get', $object->device);

        //Вычисляем значение в процентах и заносим в таблицу
        $value = round($currentValue*100/255);

        if($value != 0) $oldvalue = " ,`oldvalue` = $value";
        else $oldvalue = self::getOldValue();

        //Заносим текущее состояние в таблицу
        System::$db->query("UPDATE dimmers SET `value` = $value $oldvalue WHERE id_object = " . self::$idObject);

        //Отображение у объекта приводим в состояние "включено" или "выключено"
        if ($value>0) $object->setStatus('ON');
        else $object->setStatus('OFF');

    }

    /**
     * Включить диммер на предыдущем занчении
     */
    public function on()
    {

    }

}
