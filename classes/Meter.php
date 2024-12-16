<?php


class Meter extends ObjectManager
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
        NULL => '',
        'sec' => 'сек'
    ];

    function __construct(int $objectId = null)
    {
        if(null !== $objectId) {
            if($meter = new ObjectManager($objectId))
            {
                $this->object = $meter->object;
                $this->device = $meter->device;
            }  
        }
        else return null;
    }

    public function checkMeter()
    {
        switch($this->device->source)
        {
            case 'megad': $value = $this->getFromMegad();
                break;
            
            case 'modbus': $value = $this->getFromModbus();
                break;
            
            case 'mqtt': $value = $this->getFromMqtt();
                break;
        }

        if (!$this->validateValue($value)) return false;

        // $this->aliceCallback();
        $this->writeToGraphs($value);
        // $this->setSensorStatus();
    }

    private function getFromMegad()
    {
        $pt = Megad::getPortNum($this->device->source_id);
        $portStatus = Megad::getPortValue(
            Megad::getDeviceIdByPortNum($this->device->source_id),
            "pt={$pt}&cmd=get"
        );
        if (!isset($portStatus)) return null;
        
        Megad::getPortValue(
            Megad::getDeviceIdByPortNum($this->device->source_id),
            "pt={$pt}&cnt=0"
        );
        $impulses = (int)explode("/", $portStatus)[1];
        return $impulses * $this->device->impulse;
    }

    private function getFromModbus()
    {
        $value = Modbus::sendModbus($this->device->source_id, 'read');
        $lastTotal = $this->getLastTotal();        
        if(isset($value) && isset($lastTotal)) return $value - $lastTotal;
        else return null;
    }

    private function validateValue($value)
    {
        $logTopic = 'ERROR';
        $units = self::UNITS_MAPPING[$this->device->units];
        $error = true;

        if(!isset($value)) {
            $logMessage = "Значение = NULL : Значение не получено";
            $value = 'NULL';
        }
        elseif(!is_numeric($value)) {
            $logMessage = "Значение = $value : Некорректное значение";
            $value = 'NULL';
        }
        else {
            $logTopic = 'VALUE';
            $logMessage = "Значение = $value $units";
            $error = false;
        }
        
        echo "[$logTopic] $logMessage" . PHP_EOL;
        System::addLog(
            $logTopic, 
            "Счетчик [{$this->object->name} ID {$this->object->id}] : $logMessage",
            'sensor');
        
        if($error) return false;
        else return true;
    }

    private function writeToGraphs($value)
    {
        $timestamp = date('Y-m-d H:i:s');
        try {
            parent::$db->query("INSERT INTO meter_graphs (`meter_id`, `datetime`, `value`)
                VALUES ({$this->device->id}, '$timestamp', $value)");
            return true;
        }
        catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return false;
        } 
    }

    private function getLastTotal() {
        try {
            $sql = parent::$db->query(
                "SELECT `value` FROM `meter_graphs`
                    WHERE `meter_id` = {$this->device->id}
                    AND `value` != 0"
            );
            if($sql->rowCount() > 0)
            {
                $total = $this->device->init_value;
                while($value = $sql->fetchColumn()) {
                    $total += $value;
                }
                $total = round($total, 2);
            }
            return $total;
        }
        catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return null;
        }
    }

    public function getTotal() {
        switch($this->device->source)
        {
            case 'megad':
                try {
                    $sql = parent::$db->query(
                        "SELECT `value` FROM `meter_graphs`
                            WHERE `meter_id` = {$this->device->id}
                            AND `value` != 0"
                    );
                    if($sql->rowCount() > 0)
                    {
                        $total = $this->device->init_value;
                        while($value = $sql->fetchColumn()) {
                            $total += $value;
                        }
                        $total = round($total, 2);
                    }
                }
                catch (Exception $exception) {
                    echo $exception->getMessage() . PHP_EOL;
                }
                break;
            
            case 'modbus': $value = $this->getFromModbus();
                break;
            
            case 'mqtt': $value = $this->getFromMqtt();
                break;
        }

        if (isset($total)) return $total;
        else return false;
        
    }
}