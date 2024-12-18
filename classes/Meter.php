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
        if(isset($value)) return $value;
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

    // private function getLastTotal() {
    //     try {
    //         $sql = parent::$db->query(
    //             "SELECT `value` FROM `meter_graphs`
    //                 WHERE `meter_id` = {$this->device->id}
    //                 AND `value` != 0"
    //         );
    //         if($sql->rowCount() > 0)
    //         {
    //             $total = $this->device->init_value;
    //             while($value = $sql->fetchColumn()) {
    //                 $total += $value;
    //             }
    //             $total = round($total, 2);
    //         }
    //         return $total;
    //     }
    //     catch (Exception $exception) {
    //         echo $exception->getMessage() . PHP_EOL;
    //         return null;
    //     }
    // }

    // public function getPeriod(string $start, string $end, string $step) {
    //     try {
    //         $sql = parent::$db->query(
    //             "SELECT `datetime`, ROUND(SUM(`value`), 2) AS 'value' FROM `meter_graphs`
    //             WHERE `datetime` >= '$start'
    //             AND `datetime` < '$end'
    //             AND `meter_id` = {$this->device->id}
    //             GROUP BY $step(`datetime`)
    //             ORDER BY `datetime`"
    //         );
    //         if($sql->rowCount() > 0)
    //             return $sql->fetchAll(PDO::FETCH_KEY_PAIR);
    //         else 
    //             return null;
    //     }
    //     catch (Exception $exception) {
    //         echo $exception->getMessage() . PHP_EOL;
    //     }
    // }

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
        $ch = $this->getPeriod($se['start'], $se['end'], $se['step']);
        if(isset($ch)) {
            $columns = [];
            foreach($ch as $datetime => $value) {
                $columns[] = [
                    'date' => date($se['format'], strtotime($datetime)),
                    'value' => strval($value)
                ];
            }
            return $columns;
        }
        else return null;
    }

    public function getDay(string $date) {
        $start = date($date . ' 01:00:00');
        $end = date('Y-m-d H:i:s', strtotime($start . '+1 day'));
        $step = 'hour';
        $format = 'H';
        try {
            $sql = parent::$db->query(
                "SELECT `datetime`, ROUND(SUM(`value`), {$this->device->accuracy}) AS 'value' FROM `meter_graphs`
                WHERE `datetime` >= '$start'
                AND `datetime` < '$end'
                AND `meter_id` = {$this->device->id}
                GROUP BY $step(`datetime`)
                ORDER BY `datetime`"
            );
            if($sql->rowCount() > 0)
                return $sql->fetchAll(PDO::FETCH_KEY_PAIR);
            else 
                return null;
        }
        catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
        }
    }

    private function getDatesRange(string $start, string $end) {
        $array = [];
        for (
            $currentDate = strtotime($start);
            $currentDate <= strtotime($end);
            $currentDate += (86400)
        ) $array[] = date('Y-m-d', $currentDate);
        return $array;
    }

    public function getPeriod(string $start, string $end) {
        $days = [];
        foreach($this->getDatesRange($start, $end) as $date) {
            $days[$date] = $this->getDay($date);
        }
        return $days;
    }

    public function getPeriodTotal(string $start, string $end) {
        $r = $this->getPeriod($start, $end);
        $total = 0;
        foreach($r as $values) {
            if(!isset($values)) $values = [];
            $total += array_sum($values);
        }
        return round($total, $this->device->accuracy);
    }

    public function getPeriodTotalAlias(string $periodAlias) {
        $se = $this->getStartEnd($periodAlias);
        return  $this->getPeriodTotal($se['start'], $se['end']);
    }

    public function getTotal() {
        switch($this->device->source) {
            case 'megad':
                try {
                    $sql = parent::$db->query(
                        "SELECT ROUND(SUM(`value`), {$this->device->accuracy}) FROM `meter_graphs`
                        WHERE `meter_id` = {$this->device->id}"
                    );
                    if($sql->rowCount() > 0) {
                        $total = $this->device->init_value + $sql->fetchColumn();
                        return round($total, $this->device->accuracy);
                    }
                }
                catch (Exception $exception) {
                    echo $exception->getMessage() . PHP_EOL;
                }
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