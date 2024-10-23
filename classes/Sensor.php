<?php

/**
 * Класс работы с датчиками
 */

class Sensor extends System
{
    function __construct($idObject)
    {
        if(isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `id`, `name`, `type`, `status` FROM `objects`
                                        WHERE `id` = $idObject AND `type` = 'sensor'");
            if($sql->rowCount() > 0) {
                $result = $sql->fetch(PDO::FETCH_OBJ);
                $this->objectId = $result->id;
                $this->name = $result->name;
                $this->type = $result->type;
                $this->status = $result->status;
            }
            else {
                echo "[Error] Не найден датчик (ID $idObject)" . PHP_EOL;
            }

            $sql = parent::$db->query(" SELECT `name`, `value` FROM `sensors_properties`
                                        WHERE `object_id` = {$this->objectId}");
            if($sql->rowCount() > 0) {
                while ($row = $sql->fetch(PDO::FETCH_ASSOC)) $props[$row['name']] = $row['value'];
                $this->properties = (object)$props;
            }
            else {
                echo "[Error] Не найдены свойства датчика (ID {$this->objectId})" . PHP_EOL;
            }

            $sql = parent::$db->query(" SELECT `param_id`, `name`, `get_param`, `value`, `units`, `graph`,
                                        `min_range`, `max_range`, `min_alarm`, `max_alarm`
                                        FROM `sensors_params`
                                        WHERE `object_id` = {$this->objectId}");
            if($sql->rowCount() > 0) {
                while ($row = $sql->fetch(PDO::FETCH_ASSOC)) $param[] = $row;
                $this->params = $param;
            }
            else {
                echo "[Error] Не найдены значения параметров датчика (ID {$this->objectId})" . PHP_EOL;
            }
        }
        else
        {
            echo "[Error] Не определен ID датчика" . PHP_EOL;
        }
    }

    public function getParam($paramId)
    {
        if(isset($paramId))
        {
            foreach ($this->params as $param)
                if ($param['param_id'] == $paramId) return $param['value'];
        }
        else
        {
            echo "[Error] Не определен ID параметра датчика" . PHP_EOL;
        }
    }

    public function checkSensor()
    {
        switch($this->properties->source)
        {
            case 'megad': $this->getFromMegad();
                break;
            
            case 'modbus': $this->getFromModbus();
                break;
            
            case 'mqtt': $this->getFromMqtt();
                break;
        }
        $this->properties->timestamp = date('Y-m-d H:i:s');
        $this->validateValues();
        $this->writeValuesToDb();
        $this->writeValuesToGraphs();
        $this->setSensorStatus();
    }

    private function getFromMegad()
    {
        foreach ($this->params as $key => &$param)
        {
            if ($this->properties->connection == 'i2c')
                $query = "pt={$this->properties->sda}&scl={$this->properties->scl}&" . $param['get_param'];
            
            if ($this->properties->connection == '1w')
                $query = "pt={$this->properties->port}&{$param['get_param']}";

            if ($this->properties->connection == '1wbus')
                $query = "pt={$this->properties->port}&{$param['get_param']}{$this->properties->address}";
            
            $param['value'] = Megad::getPortValue($this->properties->source_id, $query);
            
            if ($this->properties->connection == '1w')
                $param['value'] = explode(':', $param['value'])[1];
        }
    }

    private function getFromModbus()
    {
        foreach ($this->params as $key => &$param)
        {
            $param['value'] = Modbus::sendModbus($param['get_param'], 'read');
        }
    }

    private function writeValuesToDb()
    {
        // $timestamp = date('Y-m-d H:i:s');
        foreach ($this->params as $key => $param)
        {
            parent::$db->query("UPDATE `sensors_params` SET `value` = {$param['value']}
                WHERE `object_id` = {$this->objectId} AND `param_id` = {$param['param_id']}");
        }
    }

    private function validateValues()
    {
        foreach ($this->params as $key => &$param)
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
                parent::$db->query("UPDATE `sensors_params` SET `timestamp` =  '{$this->properties->timestamp}'
                    WHERE `object_id` = {$this->objectId} AND `param_id` = {$param['param_id']}");
                
            }
            
            echo "[$logTopic] $logMessage" . PHP_EOL;
            System::addLog(
                $logTopic, 
                "Датчик [{$this->name} ID {$this->objectId}] : $logMessage",
                'sensor');
        }
    }

    private function writeValuesToGraphs()
    {
        foreach ($this->params as $key => &$param)
        {
            if ($param['graph'])
            {
                parent::$db->query("INSERT INTO sensors_graphs (`object_id`, `param_id`, `datetime`, `value`)
                    VALUES ({$this->objectId}, {$param['param_id']}, '{$this->properties->timestamp}', {$param['value']})");
            }
        }
    }

    public function setSensorStatus()
    {
        $error = false;
        foreach ($this->params as $key => $param)
        {
            if ($param['value'] == "NULL")
            {
                $sql = parent::$db->query("SELECT `timestamp` FROM `sensors_params`
                    WHERE `object_id` = {$this->objectId} AND `param_id` = {$param['param_id']}");
                if (strtotime('now') - strtotime($sql->fetch(PDO::FETCH_COLUMN)) > 1800) $error = true;
            }
        }

        $object = new Objects();
        $object->select($this->objectId);

        if ($error)
        {
            if ($object->status == 'ok')
            {
                // TODO: Отправить оповещение
            }
            $object->setStatus('error',true,false);
        }
        else $object->setStatus('ok',true,false);
    }
}