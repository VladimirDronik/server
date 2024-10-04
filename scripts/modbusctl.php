<?php

include_once "../include.php";

$modbusRtuBuses = Rs485::getBuses();
$daliBuses = Dali::getDaliBuses();

function startQueue($bus_id)
{
        $output=null;
        exec("ps aux | grep '[m]odbus_queue.php $bus_id'", $output);
        if (!$output)
        {
            exec("(php modbus_queue.php $bus_id &) >> /dev/null 2>&1");
            return true;
        }
        else return false;
}

function startPoll($bus_id)
{
        $output=null;
        exec("ps aux | grep '[m]odbus_polling_loop.php $bus_id'", $output);
        if (!$output) exec("(php modbus_polling_loop.php $bus_id &) >> /dev/null 2>&1");
}

function startDaliPoll($daliControllerId)
{
        $output=null;
        exec("ps aux | grep '[d]ali_polling_loop.php $daliControllerId'", $output);
        if (!$output) exec("(php dali_polling_loop.php $daliControllerId &) >> /dev/null 2>&1");
}

function stopQueue($bus_id)
{
    $pid = (int)exec("ps aux | grep '[p]hp modbus_queue.php $bus_id' | awk '{printf $2}'");
    if ($pid !=0) exec("kill $pid");
}

function stopPoll($bus_id)
{
    $pid = (int)exec("ps aux | grep '[p]hp modbus_polling_loop.php $bus_id' | awk '{printf $2}'");
    if ($pid !=0) exec("kill $pid");
}

function stopDaliPoll($daliControllerId)
{
    $pid = (int)exec("ps aux | grep '[p]hp dali_polling_loop.php $daliControllerId' | awk '{printf $2}'");
    if ($pid !=0) exec("kill $pid");
}

if ($argv[1] == "start")
{
    foreach ($modbusRtuBuses AS $bus_id)
    {
        if (startQueue($bus_id)) startPoll($bus_id);
    }
    foreach ($daliBuses AS $daliControllerId)
    {
        startDaliPoll($daliControllerId);
    }
}
elseif ($argv[1] == "stop")
{
    foreach ($modbusRtuBuses AS $bus_id)
    {
        stopPoll($bus_id);
        stopQueue($bus_id);
    }
    foreach ($daliBuses AS $daliControllerId)
    {
        stopDaliPoll($daliControllerId);
    }
}
