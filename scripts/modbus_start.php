<?php

include_once "../include.php";

$modbusRtuBuses = Modbus::getModbusRtuBuses();

// var_dump ($modbusRtuBuses);

foreach ($modbusRtuBuses AS $bus_id) 
{
    $output=null;
    exec("ps aux | grep '[m]odbus_queue.php $bus_id'", $output);;
    print_r($output);
    if (!$output) passthru("(php modbus_queue.php $bus_id &) >> /dev/null 2>&1");

    $output=null;
    exec("ps aux | grep '[m]odbus_polling_loop.php $bus_id'", $output);;
    print_r($output);
    if (!$output) passthru("(php modbus_polling_loop.php $bus_id &) >> /dev/null 2>&1");
}