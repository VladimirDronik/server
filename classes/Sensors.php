<?php

/**
 * Класс работы с датчиками
 */

class Sensors extends Objects
{
    private $sensor = null;
    private $source = null;

    public function __construct($idObject)
    {
        if(isset($idObject))
        {
            $sql = parent::$db->query(" SELECT *
                                        FROM `sensors` 
                                        WHERE `id_object` = $idObject");
            if($sql->rowCount() > 0)
            {
                $this->sensor = $sql->fetch(PDO::FETCH_OBJ);
                var_dump($this->sensor);
            }
            else 
            {
                echo "[Error] Датчик (ID $idObject) не найден" . PHP_EOL;
                exit(1);
            }
        }
        else
        {
            echo "[Error] Не определен ID датчика" . PHP_EOL;
            exit(1);
        }
    }

    public function getValue()
    {
        switch($this->sensor->source)
        {
            case '1w_port':
                $value = $this->get1WPortValue();
                break;
            
            case 'i2c':
                $value = $this->getI2cValue($this->sensor->parameter);
                break;

            case 'modbus':
                $value = $this->getModbusValue();
                break;

        }

        $this->writeValueToDb($value);
        return $value;
    }

    private function writeValueToDb($value)
    {
        parent::$db->query("UPDATE `sensors`
                            SET `value` = $value
                            WHERE id_object = {$this->sensor->id_object}");
    }

    private function get1WPortValue()
    {
        $sql = parent::$db->query(" SELECT `id_device`, `num_port`
                                    FROM `ports` 
                                    WHERE `object` = {$this->sensor->id_object}");
        if($sql->rowCount() > 0)
        {
            $this->source = $sql->fetch(PDO::FETCH_OBJ);
            $value = Megad::status($this->source->num_port, 'get', $this->source->id_device);
            $value = explode(':', $value)[1];
            echo "Показания датчика (ID {$this->sensor->id_object}): $value {$this->sensor->units}" . PHP_EOL;
            return $value;
        }
        else
        {
            echo "[Error] Не найден порт подключения датчика (ID {$this->sensor->id_object})" . PHP_EOL;
            exit(1);
        }
    }

    private function getI2cValue(string $parameter)
    {
        $value = Usensors::checkI2C($this->sensor->source_id)[$this->sensor->parameter];
        echo "Показания датчика (ID {$this->sensor->id_object}): $value {$this->sensor->units}" . PHP_EOL;
        return $value;
    }

    private function getModbusValue()
    {
        $value = Modbus::modbusRtu($this->sensor->source_id, 'read')['response'];
        echo "Показания датчика (ID {$this->sensor->id_object}): $value {$this->sensor->units}" . PHP_EOL;
        return $value;
    }

    private function validateValue($value)
    {
        //Проверяем является ли значение числом
        if(!is_numeric($value))
        {
            // System::addLog('error', 
            //     'Термостат "' . $this->name . '" (ID ' . $this->idObject .
            //     '). Некорректное значение ' . $termometr_value . '.',
            //     'sensor');
            // Messages::send(1,
            //     'Термостат "' . $this->name . '" (ID ' . $this->idObject .
            //     '). Некорректное значение: ' . $termometr_value . '°C.');
            return $error = true;
        }

        //Проверяем входит ли значение в диапазон измерений
        elseif (($value < $this->sensor->min_threshold) || ($value > $this->sensor->max_threshold))
        {
            // System::addLog('error', 
            //     'Термостат "' . $this->name . '" (ID ' . $this->idObject .
            //     '). Значение ' . $termometr_value . ' выходит за пределы измерения.',
            //     'sensor');
            return $error = true;
        }
        
        //Проверяем входит ли значение в диапазон аварийных значений
        else 
        {
            if ($value > $this->sensor->max_alarm) 
            {
                // System::addLog('warning',
                //     'Термостат "' . $this->name . '" (ID ' . $this->idObject .
                //     '). Значение ' . $humidity . ' ед. выше аварийного порога.',
                //     'sensor');
            }

            if ($termometr_value < $this->sensor->min_alarm)
            {
                // System::addLog('warning',
                //     'Термостат "' . $this->name . '" (ID ' . $this->idObject .
                //     '). Значение ' . $termometr_value . ' ед. ниже аварийного порога.',
                //     'sensor');
            }
            return $error = false;
        }
    }
}