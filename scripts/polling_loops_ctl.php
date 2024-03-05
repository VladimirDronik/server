<?php

include_once "../include.php";

$daliBuses = Dali::getDaliBuses();
$rsMotors = Curtain::getRsMotors();

function startDaliPoll($daliControllerId)
{
        $output=null;
        exec("ps aux | grep '[d]ali_polling_loop.php $daliControllerId'", $output);
        if (!$output) exec("(php dali_polling_loop.php $daliControllerId &) >> /dev/null 2>&1");
}

function stopDaliPoll($daliControllerId)
{
    $pid = (int)exec("ps aux | grep '[p]hp dali_polling_loop.php $daliControllerId' | awk '{printf $2}'");
    if ($pid !=0) exec("kill $pid");
}

function startRsMotorsPoll($rsMotorId)
{
        $output=null;
        exec("ps aux | grep '[c]urtain_polling_loop.php $rsMotorId'", $output);
        if (!$output) exec("(php curtain_polling_loop.php $rsMotorId &) >> /dev/null 2>&1");
}

function stopRsMotorsPoll($rsMotorId)
{
    $pid = (int)exec("ps aux | grep '[c]urtain_polling_loop.php $rsMotorId' | awk '{printf $2}'");
    if ($pid !=0) exec("kill $pid");
}


if ($argv[1] == "start")
{
    foreach ($daliBuses AS $daliControllerId) startDaliPoll($daliControllerId);
    foreach ($rsMotors AS $rsMotorId) startRsMotorsPoll($rsMotorId);
}
elseif ($argv[1] == "stop")
{
    foreach ($daliBuses AS $daliControllerId) stopDaliPoll($daliControllerId);
    foreach ($rsMotors AS $rsMotorId) stopRsMotorsPoll($rsMotorId);
}
