<?php

/**
 * Класс работы с устройствами типа регулятор (например, термостат).
 * Класс должен объединить в себе логику для любых пар датчик - устройство воздействия.
 */

class Regulator extends ObjectManager
{
    public $object;
    public $device;

    public function __construct(int $objectId = null)
    {
        if (null !== $objectId) {
            if (null !== $regulator = new ObjectManager($objectId))
            {
                $this->object = $regulator->object;
                $this->device = $regulator->device;
            } 
        }
        else return null;
    }

    public function checkRegulator()
    {
        if (null !== $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($this->device->sensors_param_id)))
        {
            if ($this->object->status)
            {
                if (null !== $sensorValue = $sensor->device->params[$this->device->sensors_param_id]['value'])
                {
                    if ($sensorValue <= ($this->device->setpoint - $this->device->hysteresis))
                    {
                        Action::runAction($this->device->lower_method);
                        echo "[INFO] Регулятор (ID {$this->object->id}) вызвал метод при значении датчика ниже уставки" . PHP_EOL;
                    }
                    if ($sensorValue >= ($this->device->setpoint + $this->device->hysteresis))
                    {
                        Action::runAction($this->device->higher_method);
                        echo "[INFO] Регулятор (ID {$this->object->id}) вызвал метод при значении датчика выше уставки" . PHP_EOL;
                    }
                } 
                else
                {
                    Action::runAction($this->device->fallback_method);
                    echo "[WARN] Регулятор (ID {$this->object->id}) вызвал аварийный метод, т.к. не получил текущее значение датчика" . PHP_EOL;
                }
            }
            else echo "[INFO] Регулятор (ID {$this->object->id}) отключен" . PHP_EOL;

            if (
                $sensor->device->params[$this->device->sensors_param_id]['value'] !== 
                $sensor->device->params[$this->device->sensors_param_id]['last_value'] ||
                date('i') == 0
            ) {
                $aliceProperties[] = [
                    "type" => "devices.properties.float",
                    "state" => [
                        "instance" => $sensor->device->params[$this->device->sensors_param_id]['param'],
                        "value" => $sensor->device->params[$this->device->sensors_param_id]['value']
                    ]
                ];
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
    }

    public function regulatorOn() {
        $this->setStatus('on');
        $this->checkRegulator(); 
    }
    
    public function regulatorOff() {
        $this->setStatus('off');
        if (null !== $this->device->fallback_method) Action::runAction($this->device->fallback_method);
        echo "[INFO] Регулятор (ID {$this->object->id}) отключен" . PHP_EOL;
    }

    public function setOptimalValue($value)
    {
        $this->device->setpoint = $value;
    }

    public function updateRegulator()
    {
        switch($this->device->source)
        {
            case 'megad':
                if (false == Megad::setThermostatSetpoint($this->device->setpoint, $this->device->source_id))
                    return false;
                break;
            
            case 'modbus':
                break;

            default:
                $this->checkRegulator();
                break;
        }
        
        $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($this->device->sensors_param_id));
        $aliceCapabilities = [
            "type" => "devices.capabilities.range",
            "state" => [
                "instance" => $sensor->device->params[$this->device->sensors_param_id]['param'],
                "value" => $this->device->setpoint
            ]
        ];
        $payload = [
            "object_id" => $this->object->id,
            "capabilities" => $aliceCapabilities,
            "properties" => null
        ];
        $mqtt = new Mqtt();
        $mqtt->publish('alice/callback', $payload, false);

        parent::$db->query("UPDATE `regulators`
            SET `setpoint` = '{$this->device->setpoint}'
            WHERE `object_id` = {$this->object->id}");

        return true;
    }

    public function getRegulatorState(){
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

    public function getRegulatorSetpoint(){
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

        parent::$db->query("UPDATE `regulators`
            SET `setpoint` = '{$this->device->setpoint}'
            WHERE `object_id` = {$this->object->id}");

        return $this->device->setpoint;
    }
}