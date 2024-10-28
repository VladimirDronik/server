<?php

/**
 * Класс работы с датчиками
 */

class Sensor extends ObjectManager
{
    public $sensor;

    function __construct(int $objectId = null)
    {
        if(null !== $objectId) {
            if($this->sensor = new ObjectManager($objectId))
                return true;
        }
        
        return false;
    }

    public function checkSensor()
    {
        switch($this->sensor->device->source)
        {
            case 'megad': $this->getFromMegad();
                break;
            
            case 'modbus': $this->getFromModbus();
                break;
            
            case 'mqtt': $this->getFromMqtt();
                break;
        }
        $this->sensor->device->timestamp = date('Y-m-d H:i:s');
        $this->validateValues();
        $this->roundValues();
        $this->writeValuesToDb();
        $this->writeValuesToGraphs();
        $this->setSensorStatus();
    }

    private function getFromMegad()
    {
        foreach ($this->sensor->device->params as $key => &$param)
        {
            if ($this->sensor->device->connection == 'i2c')
                $query = "pt={$this->sensor->device->sda}&scl={$this->sensor->device->scl}&" . $param['get_param'];
            
            if ($this->sensor->device->connection == '1w')
                $query = "pt={$this->sensor->device->port}&{$param['get_param']}";

            if ($this->sensor->device->connection == '1wbus')
                $query = "pt={$this->sensor->device->port}&{$param['get_param']}{$this->sensor->device->address}";
            
            $param['value'] = Megad::getPortValue($this->sensor->device->source_id, $query);
            
            if ($this->sensor->device->connection == '1w')
                $param['value'] = explode(':', $param['value'])[1];
        }
    }

    private function getFromModbus()
    {
        foreach ($this->sensor->device->params as $key => &$param)
        {
            $param['value'] = Modbus::sendModbus($param['get_param'], 'read');
        }
    }

    private function writeValuesToDb()
    {
        foreach ($this->sensor->device->params as $key => $param)
        {
            parent::$db->query("UPDATE `sensors_params` SET `value` = {$param['value']}
                WHERE `object_id` = {$this->sensor->object->id} AND `id` = {$param['id']}");
        }
    }

    private function validateValues()
    {
        foreach ($this->sensor->device->params as $key => &$param)
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
                parent::$db->query("UPDATE `sensors_params` SET `timestamp` =  '{$this->sensor->device->timestamp}'
                    WHERE `id` = {$param['id']}");
                
            }
            
            echo "[$logTopic] $logMessage" . PHP_EOL;
            System::addLog(
                $logTopic, 
                "Датчик [{$this->sensor->object->name} ID {$this->sensor->object->id}] : $logMessage",
                'sensor');
        }
    }

    private function roundValues()
    {
        foreach ($this->sensor->device->params as $key => &$param)
        {
            if ("NULL" != $param['value'])
                $param['value'] = round($param['value'], $param['accuracy']);
        }
    }

    private function writeValuesToGraphs()
    {
        foreach ($this->sensor->device->params as $key => &$param)
        {
            if ($param['graph'])
            {
                parent::$db->query("INSERT INTO sensors_graphs (`param_id`, `datetime`, `value`)
                    VALUES ({$param['id']}, '{$this->sensor->device->timestamp}', {$param['value']})");
            }
        }
    }

    public function setSensorStatus()
    {
        $error = false;
        foreach ($this->sensor->device->params as $key => $param)
        {
            if ($param['value'] == "NULL")
            {
                $sql = parent::$db->query(
                    "SELECT `timestamp` FROM `sensors_params` WHERE `id` = {$param['id']}"
                );
                if (strtotime('now') - strtotime($sql->fetch(PDO::FETCH_COLUMN)) > 1800) $error = true;
            }
        }

        if ($error)
        {
            if ($this->sensor->object->status == 'ok')
            {
                Messages::send(1, "Датчик {$this->sensor->object->name} (ID {$this->sensor->object->id}) неисправен");
            }
            $this->sensor->setStatus('error');
        }
        else $this->sensor->setStatus('ok');
    }

    public static function getSensorObjectIdByParamId($paramId)
    {
        $sql = parent::$db->query(
            "SELECT `object_id` FROM `sensors_params` WHERE `id` = $paramId"
        );
        
        if($sql->rowCount() > 0) return $sql->fetchColumn();
    }
}