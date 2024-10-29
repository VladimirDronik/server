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
        if (null !== $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($this->device->sensor_param_id)))
        {
            if ($this->object->status == 'on')
            {
                if (null !== $sensorValue = $sensor->device->params[$this->device->sensor_param_id]['value'])
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
                $sensor->device->params[$this->device->sensor_param_id]['value'] !== 
                $sensor->device->params[$this->device->sensor_param_id]['last_value']
            ) {
                $aliceProperties[] = [
                    "type" => "devices.properties.float",
                    "state" => [
                        "instance" => $sensor->device->params[$this->device->sensor_param_id]['param'],
                        "value" => $sensor->device->params[$this->device->sensor_param_id]['value']
                    ]
                ];
            }
            if (isset($aliceProperties)) Device::aliceCallbackState($this->object->id, null, $aliceProperties);
        }
    }

    public function regulatorOn() {
        $this->setStatus('on');
        echo "[INFO] Регулятор (ID {$this->object->id}) включен" . PHP_EOL;
        $aliceCapabilities = [
            "type" => "devices.capabilities.on_off",
            "state" => [
                "instance" => "on",
                "value" => true
            ]
        ];
        Device::aliceCallbackState($this->object->id, $aliceCapabilities, null);
        $this->checkRegulator();
        
    }
    
    public function regulatorOff() {
        $this->setStatus('off');
        if (null !== $this->device->fallback_method) Action::runAction($this->device->fallback_method);
        echo "[INFO] Регулятор (ID {$this->object->id}) отключен" . PHP_EOL;
        $aliceCapabilities = [
            "type" => "devices.capabilities.on_off",
            "state" => [
                "instance" => "on",
                "value" => false
            ]
        ];
        Device::aliceCallbackState($this->object->id, $aliceCapabilities, null);
    }

    public function setOptimalValue($value)
    {
        $this->device->setpoint = $value;
        parent::$db->query("UPDATE `regulators`
            SET `setpoint` = '{$this->device->setpoint}'
            WHERE `object_id` = {$this->object->id}");
        
        $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($this->device->sensor_param_id));
        $aliceCapabilities = [
            "type" => "devices.capabilities.range",
            "state" => [
                "instance" => $sensor->device->params[$this->device->sensor_param_id]['param'],
                "value" => $this->device->setpoint
            ]
        ];
        Device::aliceCallbackState($this->object->id, $aliceCapabilities, null);
        }
}