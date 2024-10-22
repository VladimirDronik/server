<?php

/**
 * Класс работы с устройствами типа регулятор (например, термостат).
 * Класс должен объединить в себе логику для любых пар датчик - устройство воздействия.
 */

class Regulator extends System
{
    function __construct($idObject)
    {
        if(isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `id`, `name`, `type`, `status` FROM `objects`
                                        WHERE `id` = $idObject AND `type` = 'regulator'");
            if($sql->rowCount() > 0) {
                $result = $sql->fetch(PDO::FETCH_OBJ);
                $this->objectId = $result->id;
                $this->name = $result->name;
                $this->type = $result->type;
                $this->status = $result->status;
            }
            else {
                echo "[Error] Не найден регулятор (ID $idObject)" . PHP_EOL;
                exit(1);
            }

            $sql = parent::$db->query(" SELECT `sensor_id`, `param_id`,`optimal_value`,
                `hysteresis`, `lower_method`, `higher_method`, `fallback_method`
                FROM `regulators` WHERE `object_id` = {$this->objectId}");
            if($sql->rowCount() > 0) $this->properties = $sql->fetch(PDO::FETCH_OBJ);
            else {
                echo "[Error] Не найдены свойства датчика (ID {$this->objectId})" . PHP_EOL;
                exit(1);
            }
        }
        else
        {
            echo "[Error] Не определен ID регулятора" . PHP_EOL;
            exit(1);
        }
    }


    public function checkRegulator()
    {
        if ($this->status == 'on')
        {
            if (null !== $sensorValue = (new Sensor($this->properties->sensor_id))
                ->getParam($this->properties->param_id))
            {
                if ($sensorValue <= ($this->properties->optimal_value - $this->properties->hysteresis))
                {
                    Action::runAction($this->properties->lower_method);
                    echo "[INFO] Регулятор (ID {$this->objectId}) вызвал метод при значении датчика ниже уставки" . PHP_EOL;
                }
                if ($sensorValue >= ($this->properties->optimal_value + $this->properties->hysteresis))
                {
                    Action::runAction($this->properties->higher_method);
                    echo "[INFO] Регулятор (ID {$this->objectId}) вызвал метод при значении датчика выше уставки" . PHP_EOL;
                }
            } 
            else
            {
                Action::runAction($this->properties->fallback_method);
                echo "[WARN] Регулятор (ID {$this->objectId}) вызвал аварийный метод, т.к. не получил текущее значение датчика" . PHP_EOL;
            }
        }
    }

    public function regulatorOn() {
        $object = new Objects();
        $object->select($this->objectId);
        $object->setStatus('on', true, false);
        echo "[INFO] Регулятор (ID {$this->objectId}) включен" . PHP_EOL;
    }
    
    public function regulatorOff() {
        $object = new Objects();
        $object->select($this->objectId);
        $object->setStatus('off', true, false);
        Action::runAction($this->properties['fallback_method']);
        echo "[INFO] Регулятор (ID {$this->objectId}) отключен" . PHP_EOL;
    }

    public function setOptimalValue($value)
    {
        parent::$db->query("UPDATE `regulators` SET `optimal_value` = '$value' WHERE `object_id` = {$this->objectId}");
    }
}