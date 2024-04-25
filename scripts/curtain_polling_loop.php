<?php

include_once "../include.php";

while (true)
{
    $curtain = new Curtain($argv[1]);

    // var_dump (Curtain::getRsMotorActivity($argv[1]));
    
    if (Curtain::getRsMotorActivity($argv[1])) $curtain->getPercent();
    
    sleep (6);
}