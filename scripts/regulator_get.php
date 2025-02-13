<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);

if (null !== $regulator = new Regulator($id))
{
    // if (null !== $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($regulator->device->sensors_param_id))) {
    //     $sensor->checkSensor();
    // }
    
    if (null !== $regulator->device->source) {
        if (null !== $state = $regulator->getRegulatorState()) {
            if (null !== $setpoint = $regulator->getRegulatorSetpoint()) {
                exit(0);
            }
        }
    }
    else exit(0);
}

exit(1);