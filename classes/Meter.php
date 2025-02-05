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
        if(isset($value)) {
            $result = $value - $this->device->init_value - $this->getTotalFromDb();
            return $result;
        }
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

    private function getStartEnd(string $period) {
        if($period == 'day') {
            $start = date('Y-m-d');
            $end = date('Y-m-d');
        }
        if($period == 'week') {
            if(date('D') != 'Mon') $start = date('Y-m-d', strtotime('last Monday'));
            else $start = date('Y-m-d');
            if(date('D') != 'Sun') $end = date('Y-m-d', strtotime('next Sunday'));
            else $end = date('Y-m-d');
        }
        if($period == 'month') {
            $start = date('Y-m-01');
            $end = date('Y-m-t');
        }
        if($period == 'year') {
            $start = date('Y-01-01');
            $end = date('Y-12-31');
        }
        return ['start' => $start, 'end' => $end];
    }

    public function getChart(string $period) {
        $se = $this->getStartEnd($period);
        $ch = $this->getPeriod($se['start'], $se['end']);

        $template =[];
        $data = [];
        if($period == 'day') {
            for($i=0; $i<24; $i++) {
                $template[date("H", strtotime($se['start'] . "+$i hour"))] = 0;
            }
            foreach($ch as $key => $value) {
                $data[date('H', strtotime($key))] = $value;
            }
        }
        if($period == 'month') {
            for($i=0; $i<date('t'); $i++) {
                $template[date("d", strtotime($se['start'] . "+$i day"))] = 0;
            }
            foreach($ch as $key => $value) {
                $data[date('d', strtotime($key))] = $value;
            }
        }
        if($period == 'year') {
            for($i=0; $i<date('t'); $i++) {
                $template[date("M", strtotime($se['start'] . "+$i month"))] = 0;
            }
            foreach($ch as $key => $value) {
                $data[date('M', strtotime($key))] = $value;
            }
        }
        return array_replace($template, $data);
    }

    public function getPeriod(string $start, string $end) {
        if((strtotime($end)-strtotime($start) < 86400)) $step = 'hour';
        elseif((strtotime($end)-strtotime($start) < 2678400)) $step = 'day';
        else $step = 'month';

        try {
            $sql = parent::$db->query(
                "SELECT `datetime`,
                ROUND(SUM(`value`), {$this->device->accuracy}) AS 'value'
                FROM `meter_graphs`
                WHERE `datetime` >= '$start 00:00:00'
                AND `datetime` <= '$end 23:59:59'
                AND `meter_id` = {$this->device->id}
                GROUP BY $step(`datetime`)
                ORDER BY `datetime`"
            );
            if($sql->rowCount() > 0) return $sql->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
        }
        return [];
    }

    public function getPeriodTotal(string $start, string $end) {
        $total = array_sum($this->getPeriod($start, $end));
        return round($total, $this->device->accuracy);
    }

    public function getPeriodTotalAlias(string $periodAlias) {
        $se = $this->getStartEnd($periodAlias);
        return  $this->getPeriodTotal($se['start'], $se['end']);
    }

    public function getTotalFromDb() {
        try {
            $sql = parent::$db->query(
                "SELECT ROUND(SUM(`value`), {$this->device->accuracy}) FROM `meter_graphs`
                WHERE `meter_id` = {$this->device->id}"
            );
            if($sql->rowCount() > 0) {
                return round($sql->fetchColumn(), $this->device->accuracy);
            }
        }
        catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return null;
        }
    }

    public function getTotal() {
        switch($this->device->source) {
            case 'megad': return $this->device->init_value + $this->getTotalFromDb();
                break;
            
            case 'modbus':
                $value = Modbus::sendModbus($this->device->source_id, 'read');
                if(isset($value)) return $value;
                else return null;
                break;
            
            case 'mqtt': $value = $this->getFromMqtt();
                break;
        }
        return null;
    }

}