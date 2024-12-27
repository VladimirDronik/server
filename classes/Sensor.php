<?php

/**
 * Класс работы с датчиками
 */

class Sensor extends ObjectManager
{
    public $object;
    public $device;

    const UNITS_MAPPING = [
        'celsius' => '°C',
        'percent' => '%',
        'ppm' => 'ppm',
        'atm' => 'атм',
        'pascal' => 'Па',
        'bar' => 'бар',
        'mmhg' => 'мм рт.ст.',
        'lux' => 'люкс',
        'ampere' => 'А',
        'kilowatt_hour' => 'кВт/ч',
        'cubic_meter' => 'м³',
        'gigacalorie' => 'Гкал',
        'mcg_m3'  => 'мкг/м³',
        'watt' => 'Вт',
        'kelvin' => 'К',
        'volt' => 'В',
        NULL => ''
    ];

    function __construct(int $objectId = null)
    {
        if(null !== $objectId) {
            if($sensor = new ObjectManager($objectId))
            {
                $this->object = $sensor->object;
                $this->device = $sensor->device;
            }  
        }
        else return null;
    }

    public function checkSensor()
    {
        switch($this->device->source)
        {
            case 'megad': $this->getFromMegad();
                break;
            
            case 'modbus': $this->getFromModbus();
                break;
            
            case 'mqtt': $this->getFromMqtt();
                break;
        }
        $this->device->timestamp = date('Y-m-d H:i:s');
        $this->validateValues();
        $this->roundValues();
        $this->writeValuesToDb();
        $this->aliceCallback();
        $this->writeValuesToGraphs();
        $this->setSensorStatus();
    }

    private function getFromMegad()
    {
        foreach ($this->device->params as $key => &$param)
        {
            if ($this->device->connection == 'i2c') {
                $sda = Megad::getPortNum($this->device->sda);
                $scl = Megad::getPortNum($this->device->scl);
                $query = "pt={$sda}&scl={$scl}&" . $param['get_param'];
            }

            if ($this->device->connection == '1w') {
                $pt = Megad::getPortNum($this->device->port);
                $query = "pt={$pt}&{$param['get_param']}";
            }

            if ($this->device->connection == '1wbus') {
                $pt = Megad::getPortNum($this->device->port);
                $query = "pt={$pt}&{$param['get_param']}";
            }
                
            $param['value'] = Megad::getPortValue($this->device->source_id, $query);
            
            if ($this->device->connection == '1w')
                $param['value'] = explode(':', $param['value'])[1];
        }
    }

    private function getFromModbus()
    {
        foreach ($this->device->params as $key => &$param)
        {
            $param['value'] = Modbus::sendModbus($param['get_param'], 'read');
        }
    }

    private function writeValuesToDb()
    {
        foreach ($this->device->params as $key => $param)
        {
            parent::$db->query("UPDATE `sensors_params` SET `value` = {$param['value']}
                WHERE `object_id` = {$this->object->id} AND `id` = {$param['id']}");
        }
    }

    private function validateValues()
    {
        foreach ($this->device->params as $key => &$param)
        {
            $logTopic = 'ERROR';
            $units = self::UNITS_MAPPING[$param['units']];

            if(!isset($param['value']))
            {
                $logMessage = "{$param['name']} = NULL : Значение не получено";
                $param['value'] = 'NULL';
            }
            elseif(!is_numeric($param['value']))
            {
                $logMessage = "{$param['name']} = {$param['value']} {} : Некорректное значение";
                $param['value'] = 'NULL';
            }
            elseif(
                (isset($param['min_range']) && $param['value'] < $param['min_range']) ||
                (isset($param['max_range']) && $param['value'] > $param['max_range']))
            {
                $logMessage = "{$param['name']} = {$param['value']} $units : Значение {$param['value']} вне диапазона измерений";
                $param['value'] = 'NULL';
            }
            elseif (isset($param['max_alarm']) && $param['value'] > $param['max_alarm']) 
            {
                $logMessage = "{$param['name']} = {$param['value']} $units : Значение {$param['value']} выше аварийного порога";
            }

            elseif (isset($param['min_alarm']) && $param['value'] < $param['min_alarm'])
            {
                $logMessage = "{$param['name']} = {$param['value']} $units : Значение {$param['value']} ниже аварийного порога";
            }
            else
            {
                $logTopic = 'VALUE';
                $logMessage = "{$param['name']} = {$param['value']} $units";
                parent::$db->query("UPDATE `sensors_params` SET `timestamp` =  '{$this->device->timestamp}'
                    WHERE `id` = {$param['id']}");
                
            }
            
            echo "[$logTopic] Датчик {$this->object->name} ID {$this->object->id} : $logMessage" . PHP_EOL;
            System::addLog(
                $logTopic, 
                "Датчик [{$this->object->name} ID {$this->object->id}] : $logMessage",
                'sensor');
        }
    }

    private function roundValues()
    {
        foreach ($this->device->params as $key => &$param)
        {
            if ("NULL" != $param['value'])
                $param['value'] = round($param['value'], $param['accuracy']);
        }
    }

    private function writeValuesToGraphs()
    {
        foreach ($this->device->params as $key => &$param)
        {
            if ($param['graph'])
            {
                parent::$db->query("INSERT INTO sensor_graphs (`param_id`, `datetime`, `value`)
                    VALUES ({$param['id']}, '{$this->device->timestamp}', {$param['value']})");
            }
        }
    }

    private function aliceCallback()
    {
        foreach ($this->device->params as $key => $param)
        {
            if ($param['value'] !== $param['last_value'] || date('i') == 0) {
                $aliceProperties[] = [
                    "type" => "devices.properties.float",
                    "state" => [
                        "instance" => $param['param'],
                        "value" => $param['value']
                    ]
                ];
            }
        }
        if (isset($aliceProperties)) {
            $payload = [
                "object_id" => $this->object->id,
                "capabilities" => null,
                "properties" =>$aliceProperties
            ];
            $mqtt = new Mqtt();
            $mqtt->publish('alice/callback', $payload, false);
        }
    }

    public function setSensorStatus()
    {
        $error = false;
        foreach ($this->device->params as $key => $param)
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
            if ($this->object->status == 'ok')
            {
                Messages::send(1, "Датчик {$this->object->name} (ID {$this->object->id}) неисправен");
            }
            $this->setStatus('error');
        }
        else $this->setStatus('ok');
    }

    public static function getSensorObjectIdByParamId($paramId)
    {
        $sql = parent::$db->query(
            "SELECT `object_id` FROM `sensors_params` WHERE `id` = $paramId"
        );
        
        if($sql->rowCount() > 0) return $sql->fetchColumn();
    }
}