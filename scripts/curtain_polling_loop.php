<?php

include_once "../include.php";

while (true)
{
    $curtain = new Curtain($argv[1]);
    $curtain->getInfo();
    sleep (2);
    $motorState = System::getVariable("rsMotor_$argv[1]");
    if ($motorState == 0) $curtain->getPercent();
    sleep (8);
}