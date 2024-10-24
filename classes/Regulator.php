<?php

/**
 * Класс работы с устройствами типа регулятор (например, термостат).
 * Класс должен объединить в себе логику для любых пар датчик - устройство воздействия.
 */

class Regulator extends System
{
    public $object;
    public $regulator;
    public $sensor;

    public function __construct($idObject)
    {
        if(isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `sensor_id`, `param_id`,`optimal_value`,
                `hysteresis`, `lower_method`, `higher_method`, `fallback_method`
                FROM `regulators` WHERE `object_id` = $idObject");
            
            if($sql->rowCount() > 0) {
                $this->regulator = $sql->fetch(PDO::FETCH_OBJ);
                $this->object = new Objects();
                $this->object->select($idObject);
                $this->sensor = new Sensor($this->regulator->sensor_id);
            }
            else echo "[Error] Не найден регулятор (ID {$this->objectId})" . PHP_EOL;
        }
        else echo "[Error] Не определен ID регулятора" . PHP_EOL;
    }


    public function checkRegulator()
    {
        if ($this->object->status == 'on')
        {
            if (null !== $sensorValue = $this->sensor->sensor->params[$this->regulator->param_id]['value'])
            {
                if ($sensorValue <= ($this->regulator->optimal_value - $this->regulator->hysteresis))
                {
                    Action::runAction($this->regulator->lower_method);
                    echo "[INFO] Регулятор (ID {$this->object->id}) вызвал метод при значении датчика ниже уставки" . PHP_EOL;
                }
                if ($sensorValue >= ($this->regulator->optimal_value + $this->regulator->hysteresis))
                {
                    Action::runAction($this->regulator->higher_method);
                    echo "[INFO] Регулятор (ID {$this->object->id}) вызвал метод при значении датчика выше уставки" . PHP_EOL;
                }
            } 
            else
            {
                Action::runAction($this->regulator->fallback_method);
                echo "[WARN] Регулятор (ID {$this->object->id}) вызвал аварийный метод, т.к. не получил текущее значение датчика" . PHP_EOL;
            }
        }
    }

    public function regulatorOn() {
        $this->object->setStatus('on', true, false);
        echo "[INFO] Регулятор (ID {$this->object->id}) включен" . PHP_EOL;
    }
    
    public function regulatorOff() {
        $this->object->setStatus('off', true, false);
        Action::runAction($this->regulator->fallback_method);
        echo "[INFO] Регулятор (ID {$this->object->id}) отключен" . PHP_EOL;
    }

    public function setOptimalValue($value)
    {
        $this->regulator->optimal_value = $value;
        parent::$db->query("UPDATE `regulators`
            SET `optimal_value` = '{$this->regulator->optimal_value}'
            WHERE `object_id` = {$this->object->id}");
    }
}