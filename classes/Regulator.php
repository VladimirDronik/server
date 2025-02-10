<?php

/**
 * Класс работы с устройствами типа регулятор (например, термостат).
 * Класс должен объединить в себе логику для любых пар датчик - устройство воздействия.
 */

class Regulator extends ObjectManager
{
    public $object;
    public $device;
    public $sensor;

    public function __construct(int $objectId = null)
    {
        if (null !== $objectId) {
            if (null !== $regulator = new ObjectManager($objectId))
            {
                $this->object = $regulator->object;
                $this->device = $regulator->device;
                $this->sensor = Sensor::getParamValue($this->device->sensors_param_id);
            }
        }
        else return null;
    }

    public function checkRegulator()
    {
        if (null !== $this->device->source)
        {
            if (
                null === $this->getRegulatorState() ||
                null === $this->getRegulatorSetpoint()
            ) return false;
            return true;
        }
        else
        {
            if ($this->object->status == 'on')
            {
                if (null !== $this->sensor->value)
                {
                    if ($this->sensor->value <= ($this->device->setpoint - $this->device->hysteresis))
                    {
                        Action::runAction(
                            $this->device->lower_method,
                            'regulator',
                            $this->object->id,
                            null,
                            $this->device->lower_method_params
                        );
                        echo "[INFO] Регулятор (ID {$this->object->id}) вызвал метод при значении датчика ниже уставки" . PHP_EOL;
                        $this->device->current_method = 'lower';
                    }
                    elseif ($this->sensor->value >= ($this->device->setpoint + $this->device->hysteresis))
                    {
                        Action::runAction(
                            $this->device->higher_method,
                            'regulator',
                            $this->object->id,
                            null, 
                            $this->device->higher_method_params
                        );
                        echo "[INFO] Регулятор (ID {$this->object->id}) вызвал метод при значении датчика выше уставки" . PHP_EOL;
                        $this->device->current_method = 'higher';
                    }
                    else
                    {
                        echo "[INFO] Регулятор (ID {$this->object->id}): действие не требуется" . PHP_EOL;
                        $this->device->current_method = null;
                    }
                } 
                else
                {
                    Action::runAction(
                        $this->device->fallback_method,
                        'regulator',
                        $this->object->id,
                        null,
                        $this->device->fallback_method_params
                    );
                    echo "[WARN] Регулятор (ID {$this->object->id}) вызвал аварийный метод, т.к. не получил текущее значение датчика" . PHP_EOL;
                    $this->device->current_method = 'fallback';
                }
            }
            else 
            {
                if (null !== $this->device->fallback_method)
                    Action::runAction(
                        $this->device->fallback_method,
                        'regulator',
                        $this->object->id,
                        null,
                        $this->device->fallback_method_params
                    );
                echo "[INFO] Регулятор (ID {$this->object->id}) был отключен, вызван аварийный метод" . PHP_EOL;
                $this->device->current_method = 'fallback';
            }

            // $this->checkChanges();

            return true;
        }
    }

    private function checkChanges() {
        $params = null;

        $sql = parent::$db->query(
            "SELECT * FROM `regulator_graphs`
            WHERE `regulator_id` = {$this->device->id}
            AND `datetime` IN
            (SELECT max(`datetime`) FROM `regulator_graphs`
            WHERE `regulator_id` = {$this->device->id})"
        );
        if ($sql->rowCount() > 0) {
            while ($param = $sql->fetch(PDO::FETCH_OBJ)) {
                $params[$param->param] = $param->value;
		    }
        }

        $sql = parent::$db->query(
            "SELECT `value` FROM `sensor_graphs`
            WHERE `param_id` = {$this->device->sensors_param_id}
            AND `datetime` IN
            (SELECT max(`datetime`) FROM `sensor_graphs`
            WHERE `param_id` = {$this->device->sensors_param_id})"
        );
        if ($sql->rowCount() > 0) {
            $this->sensor->last_value = $sql->fetchColumn();
        }

        if (
            $params['setpoint'] != $this->device->setpoint ||
            $params['state'] != $this->object->status ||
            $this->sensor->last_value != $this->sensor->value
        ) {
            if ($params['setpoint'] != $this->device->setpoint)
                System::addLog(
                    'Уставка', 
                    "Регулятор [{$this->object->name} ID {$this->object->id}] : " .
                    "Изменена уставка {$params['setpoint']} -> {$this->device->setpoint}",
                    'regulator'
                );

            if ($params['state'] != $this->object->status) {
                if ($this->object->status == 'on') $s = 'автоматический';
                else $s = 'ручной';
                System::addLog(
                    'Режим', 
                    "Регулятор [{$this->object->name} ID {$this->object->id}] : " .
                    "Переведен в $s режим управления",
                    'regulator'
                );
            }          

            $this->writeToGraphs();
            $this->aliceCallback();
        }
        elseif (
            null !== $this->device->current_method &&
            $params['method'] != $this->device->current_method
        ) {
            if ($this->device->current_method == 'lower') $m = "метод ниже уставки";
            if ($this->device->current_method == 'higher') $m = "метод выше уставки";
            if ($this->device->current_method == 'fallback') $m = "аварийный метод";
            System::addLog(
                'Метод', 
                "Регулятор [{$this->object->name} ID {$this->object->id}] : " .
                "Вызвал $m",
                'regulator'
            );
            $this->writeToGraphs();
        }
    }

    private function writeToGraphs() {
        parent::$db->query(
            "INSERT INTO regulator_graphs (`regulator_id`, `param`, `datetime`, `value`)
            VALUES ({$this->device->id}, 'setpoint', CONCAT(CURRENT_DATE,' ',CURRENT_TIME), '{$this->device->setpoint}')"
        );
        parent::$db->query(
            "INSERT INTO regulator_graphs (`regulator_id`, `param`, `datetime`, `value`)
            VALUES ({$this->device->id}, 'state', CONCAT(CURRENT_DATE,' ',CURRENT_TIME), '{$this->object->status}')"
        );
        if (null === $this->device->source)
        {
            parent::$db->query(
                "INSERT INTO regulator_graphs (`regulator_id`, `param`, `datetime`, `value`)
                VALUES ({$this->device->id}, 'method', CONCAT(CURRENT_DATE,' ',CURRENT_TIME), '{$this->device->current_method}')"
            );
        }
    }

    private function aliceCallback() {
        $aliceProperties = [];
        $aliceProperties[] = [
            "type" => "devices.properties.float",
            "state" => [
                "instance" => $this->sensor->param,
                "value" => $this->sensor->value
            ]
        ];

        $aliceCapabilities = [];
        $aliceCapabilities[] = [
            "type" => "devices.capabilities.range",
            "state" => [
                "instance" => $this->sensor->param,
                "value" => $this->device->setpoint
            ]
        ];

        if ($this->object->status == "on") $s = true;
        else $s = false;

        $aliceCapabilities[] = [
            "type" => "devices.capabilities.on_off",
            "state" => [
                "instance" => "on",
                "value" => $s
            ]
        ];
        $payload = [
            "object_id" => $this->object->id,
            "capabilities" => $aliceCapabilities,
            "properties" => $aliceProperties
        ];
        $mqtt = new Mqtt();
        $mqtt->publish('alice/callback', $payload, false);
    }

    private function saveToDb() {
        parent::$db->query(
            "UPDATE `regulators`
            SET `setpoint` = '{$this->device->setpoint}'
            WHERE `object_id` = {$this->object->id}"
        );
    }

    public function setSetpoint($value) {
        $this->device->setpoint = $value;
    }

    public function setState($value) {
        $this->object->status = $value;
    }

    public function updateRegulator()
    {
        switch($this->device->source)
        {
            case 'megad':
                if (
                    false === Megad::setThermostatSetpoint($this->device->setpoint, $this->device->source_id) ||
                    false === Megad::setThermostatStatus($this->object->status, $this->device->source_id)
                ) return false;
                break;

            case 'modbus':
                if ($this->object->status == 'on') $s = 1;
                else $s = 0;
                $status = Modbus::sendModbus(
                    Modbus::getRegisterIdByAlias($this->device->source_id, "status"),
                    'write',
                    $s
                );
                $setpoint = Modbus::sendModbus(
                    Modbus::getRegisterIdByAlias($this->device->source_id, "setpoint"),
                    'write',
                    $this->device->setpoint
                );
                if (null === $status || null === $setpoint) return false;
                break;

            default:
                if (!$this->checkRegulator()) return false;
                break;
        }

        $this->getRegulatorState();
        $this->getRegulatorSetpoint();
        $this->checkChanges();

        return true;
    }

    public function getRegulatorState(){
        if (isset($this->device->source)) {
            switch($this->device->source)
            {
                case 'megad':
                    $response = Megad::getThermostatState($this->device->source_id);
                    break;
                
                case 'modbus':
                    $response = Modbus::sendModbus(
                        Modbus::getRegisterIdByAlias($this->device->source_id, "state"),
                        'read'
                    ); 
                    break;
            }

            if (isset($response)) {
                if ($response) $this->object->status = 'on';
                else $this->object->status = 'off';
                $this->setStatus($this->object->status);
                return $this->object->status;
            }
            else return null;
        }
        else
        {
            $this->setStatus($this->object->status);
            return $this->object->status;
        }
    }

    public function getRegulatorSetpoint(){
        if (isset($this->device->source)) {
            switch($this->device->source)
            {
                case 'megad':
                    $response = Megad::getThermostatSetpoint($this->device->source_id);
                    break;
                
                case 'modbus':
                    $response = Modbus::sendModbus(
                        Modbus::getRegisterIdByAlias($this->device->source_id, "setpoint"),
                        'read'
                    );
                    break;
            }
    
            if (isset($response)) $this->device->setpoint = $response;
            else return null;
        }
        
        parent::$db->query("UPDATE `regulators`
            SET `setpoint` = {$this->device->setpoint}
            WHERE `object_id` = {$this->object->id}");

        return $this->device->setpoint;
    }

    public static function getObjectIdBySensorParamId($sensorParamId)
    {
        $sql = parent::$db->query(
            "SELECT `object_id` FROM `regulators` WHERE `sensors_param_id` = $sensorParamId"
        );
        if($sql->rowCount() > 0) return $sql->fetchColumn();
    }
}