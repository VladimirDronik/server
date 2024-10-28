<?php

/**
 * Класс работы с устройствами типа регулятор (например, термостат).
 * Класс должен объединить в себе логику для любых пар датчик - устройство воздействия.
 */

class Regulator extends System
{
    public $regulator;
    public $sensor;
    public $controllDevice;

    public function __construct(int $objectId = null)
    {
        if(null !== $objectId) {
            if($this->regulator = new ObjectManager($objectId))
                if($this->sensor = new ObjectManager(
                        Sensor::getSensorObjectIdByParamId($this->regulator->device->sensor_param_id)
                )) 
                    if($this->controllDevice = new ObjectManager(
                            ObjectManager::getObjectIdByMethod($this->regulator->device->lower_method)
                    ))
                        return true;
        }
        return false;
    }

    public function checkRegulator()
    {
        if ($this->regulator->object->status == 'on')
        {
            if (null !== $sensorValue = $this->sensor->device->params[$this->regulator->device->sensor_param_id]['value'])
            {
                if ($sensorValue <= ($this->regulator->device->setpoint - $this->regulator->device->hysteresis))
                {
                    Action::runAction($this->regulator->device->lower_method);
                    echo "[INFO] Регулятор (ID {$this->regulator->object->id}) вызвал метод при значении датчика ниже уставки" . PHP_EOL;
                }
                if ($sensorValue >= ($this->regulator->device->setpoint + $this->regulator->device->hysteresis))
                {
                    Action::runAction($this->regulator->device->higher_method);
                    echo "[INFO] Регулятор (ID {$this->regulator->object->id}) вызвал метод при значении датчика выше уставки" . PHP_EOL;
                }
            } 
            else
            {
                Action::runAction($this->regulator->device->fallback_method);
                echo "[WARN] Регулятор (ID {$this->regulator->object->id}) вызвал аварийный метод, т.к. не получил текущее значение датчика" . PHP_EOL;
            }
        }
    }

    public function regulatorOn() {
        $this->regulator->setStatus('on');
        echo "[INFO] Регулятор (ID {$this->regulator->object->id}) включен" . PHP_EOL;
    }
    
    public function regulatorOff() {
        $this->regulator->setStatus('off');
        Action::runAction($this->regulator->device->fallback_method);
        echo "[INFO] Регулятор (ID {$this->regulator->object->id}) отключен" . PHP_EOL;
    }

    public function setOptimalValue($value)
    {
        $this->regulator->device->setpoint = $value;
        parent::$db->query("UPDATE `regulators`
            SET `setpoint` = '{$this->regulator->device->setpoint}'
            WHERE `object_id` = {$this->regulator->object->id}");
    }
}