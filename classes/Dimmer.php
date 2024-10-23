<?php

/**
 * Class Dimmer позволяет работать с диммируемыми портами на контроллере
 * как будто мы работаем с отдельным устройством
 */

class Dimmer extends System
{
    private $deviceTable;
    public $dimmer;
    public $object;

    function __construct($idObject)
    {
        if (null != $idObject)
        {
            $deviceTables = ['lamps', 'dimmers'];
            foreach ($deviceTables as $table)
            {
                $sql = parent::$db->query("SELECT * FROM `$table` WHERE `id_object` = $idObject");
                if($sql->rowCount() > 0) {
                    $this->dimmer = $sql->fetch(PDO::FETCH_OBJ);
                    $this->object = new Objects();
                    $this->object->select($idObject);
                    $this->deviceTable = $table;
                    break;
                }
            }

            if (null == $this->dimmer) echo "[Error] Объект не найден (ID $idObject)" . PHP_EOL;
        }
        else echo "[Error] Не определен ID объекта" . PHP_EOL;
    }

    /**
     * Установка скорости смены порта диммера
     * @param int @value - значение скорости, которое хотим установить
     */
    public function setSpeed($speed)
    {
        $this->dimmer->speed = $speed;
        parent::$db->query("UPDATE `".self::$deviceTable."` SET `speed` = $speed WHERE id_object = ".self::$idObject);
    }

    /**
     * Установка значения яркости для диммера
     * @param int @value - значение яркости, которое хотим установить
     */
    public function setValue($value)
    {
        $mega = new Megad();

        if ($this->object->portstate == "OUT")
        {
            $valuePWM = round(255*$value/100);
            //Отправляем данные на порт контроллера
            $mega->setPWM($this->object->port, $valuePWM, $this->object->device, $this->dimmer->speed);
        }
            elseif ($this->object->portstate == "0..10V")
        {
            //Отправляем данные на модуль 0-10В
            $mega->setValueToDimmerExt($this->object->device, $this->object->port, $value);
        }

        if($value > 0) {
            //Заносим текущее состояние в таблицу
            parent::$db->query("UPDATE `{$this->deviceTable}` SET `value` = $value WHERE `id_object` = {$this->dimmer->id_object}");
            $this->object->setStatus('on', true, false);
            $aliceCapabilities = [
                "type" => "devices.capabilities.range",
                "state" => [
                    "instance" => "brightness",
                    "value" => $value
                ]
            ];
            Device::aliceCallbackState($this->dimmer->id_object, $aliceCapabilities, null);
        }
        else $this->object->setStatus('off', true, false);
        
        
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
        // $sql = parent::$db->query("SELECT `value` FROM `{$this->deviceTable}` WHERE `id_object` = {$this->dimmer->id_object}");
        // $dimer = $sql->fetch(PDO::FETCH_OBJ);

        return $this->dimmer->value;
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
