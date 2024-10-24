<?php

/**
 * Класс работы с датчиками
 */

class Sensor extends System
{
    public $object;
    public $sensor;

    function __construct($idObject)
    {
        if(isset($idObject))
        {
            $this->object = new Objects();

            if (null === $this->object->select($idObject))
                echo "[Error] Не найден датчик (ID $idObject)" . PHP_EOL;

            $sql = parent::$db->query(" SELECT `name`, `value` FROM `sensors_properties`
                                        WHERE `object_id` = $idObject");
            if($sql->rowCount() > 0) {
                while ($row = $sql->fetch(PDO::FETCH_ASSOC)) $props[$row['name']] = $row['value'];
                $this->sensor = (object)$props;
            }
            else echo "[Error] Не найдены свойства датчика (ID $idObject)" . PHP_EOL;

            $sql = parent::$db->query(" SELECT `param_id`, `name`, `get_param`, `value`, `units`, `graph`,
                                        `min_range`, `max_range`, `min_alarm`, `max_alarm`
                                        FROM `sensors_params`
                                        WHERE `object_id` = $idObject");
            if($sql->rowCount() > 0) {
                while ($row = $sql->fetch(PDO::FETCH_ASSOC)) $param[$row['param_id']] = $row;
                $this->sensor->params = $param;
            }
            else echo "[Error] Не найдены значения параметров датчика (ID $idObject)" . PHP_EOL;
        }
        else echo "[Error] Не определен ID датчика" . PHP_EOL;
    }

    public function getParam($paramId) :array
    {
        if(isset($paramId))
        {
            return [
                $this->sensor->params[$paramId]['value'],
                $this->sensor->params[$paramId]['units']
            ];
        }
        else 
        {
            echo "[Error] Не определен ID параметра датчика" . PHP_EOL;
            return null;
        }
    }

    public function checkSensor()
    {
        switch($this->sensor->source)
        {
            case 'megad': $this->getFromMegad();
                break;
            
            case 'modbus': $this->getFromModbus();
                break;
            
            case 'mqtt': $this->getFromMqtt();
                break;
        }
        $this->sensor->timestamp = date('Y-m-d H:i:s');
        $this->validateValues();
        $this->roundValues();
        $this->writeValuesToDb();
        $this->writeValuesToGraphs();
        $this->setSensorStatus();
    }

    private function getFromMegad()
    {
        foreach ($this->sensor->params as $key => &$param)
        {
            if ($this->sensor->connection == 'i2c')
                $query = "pt={$this->sensor->sda}&scl={$this->sensor->scl}&" . $param['get_param'];
            
            if ($this->sensor->connection == '1w')
                $query = "pt={$this->sensor->port}&{$param['get_param']}";

            if ($this->sensor->connection == '1wbus')
                $query = "pt={$this->sensor->port}&{$param['get_param']}{$this->sensor->address}";
            
            $param['value'] = Megad::getPortValue($this->sensor->source_id, $query);
            
            if ($this->sensor->connection == '1w')
                $param['value'] = explode(':', $param['value'])[1];
        }
    }

    private function getFromModbus()
    {
        foreach ($this->sensor->params as $key => &$param)
        {
            $param['value'] = Modbus::sendModbus($param['get_param'], 'read');
        }
    }

    private function writeValuesToDb()
    {
        foreach ($this->sensor->params as $key => $param)
        {
            parent::$db->query("UPDATE `sensors_params` SET `value` = {$param['value']}
                WHERE `object_id` = {$this->object->id} AND `param_id` = {$param['param_id']}");
        }
    }

    private function validateValues()
    {
        foreach ($this->sensor->params as $key => &$param)
        {
            $logTopic = 'ERROR';

            if(!isset($param['value']))
            {
                $logMessage = "{$param['name']} = NULL : Значение не получено";
                $param['value'] = 'NULL';
            }
            elseif(!is_numeric($param['value']))
            {
                $logMessage = "{$param['name']} = {$param['value']} {$param['units']} : Некорректное значение";
                $param['value'] = 'NULL';
            }
            elseif(
                (isset($param['min_range']) && $param['value'] < $param['min_range']) ||
                (isset($param['max_range']) && $param['value'] > $param['max_range']))
            {
                $logMessage = "{$param['name']} = {$param['value']} {$param['units']} : Значение {$param['value']} вне диапазона измерений";
                $param['value'] = 'NULL';
            }
            elseif (isset($param['max_alarm']) && $param['value'] > $param['max_alarm']) 
            {
                $logMessage = "{$param['name']} = {$param['value']} {$param['units']} : Значение {$param['value']} выше аварийного порога";
            }

            elseif (isset($param['min_alarm']) && $param['value'] < $param['min_alarm'])
            {
                $logMessage = "{$param['name']} = {$param['value']} {$param['units']} : Значение {$param['value']} ниже аварийного порога";
            }
            else
            {
                $logTopic = 'VALUE';
                $logMessage = "{$param['name']} = {$param['value']} {$param['units']}";
                parent::$db->query("UPDATE `sensors_params` SET `timestamp` =  '{$this->sensor->timestamp}'
                    WHERE `object_id` = {$this->object->id} AND `param_id` = {$param['param_id']}");
                
            }
            
            echo "[$logTopic] $logMessage" . PHP_EOL;
            System::addLog(
                $logTopic, 
                "Датчик [{$this->object->name} ID {$this->object->id}] : $logMessage",
                'sensor');
        }
    }

    private function roundValues()
    {
        foreach ($this->sensor->params as $key => &$param)
        {
            $param['value'] = round($param['value'], $this->sensor->params['accuracy']);
        }
    }

    private function writeValuesToGraphs()
    {
        foreach ($this->sensor->params as $key => &$param)
        {
            if ($param['graph'])
            {
                parent::$db->query("INSERT INTO sensors_graphs (`object_id`, `param_id`, `datetime`, `value`)
                    VALUES ({$this->object->id}, {$param['param_id']}, '{$this->sensor->timestamp}', {$param['value']})");
            }
        }
    }

    public function setSensorStatus()
    {
        $error = false;
        foreach ($this->sensor->params as $key => $param)
        {
            if ($param['value'] == "NULL")
            {
                $sql = parent::$db->query("SELECT `timestamp` FROM `sensors_params`
                    WHERE `object_id` = {$this->object->id} AND `param_id` = {$param['param_id']}");
                if (strtotime('now') - strtotime($sql->fetch(PDO::FETCH_COLUMN)) > 1800) $error = true;
            }
        }

        if ($error)
        {
            if ($this->object->status == 'ok')
            {
                Messages::send(1, "Датчик {$this->name} (ID {$this->objectId}) неисправен");
            }
            $this->object->setStatus('error',true,false);
        }
        else $this->object->setStatus('ok',true,false);
    }
}