<?php

include_once "../include.php";

while (true)
{
    $curtain = new Curtain($argv[1]);
    $curtain->getPercent();
    sleep (10);
}