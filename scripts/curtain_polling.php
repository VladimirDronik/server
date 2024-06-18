<?php

include_once "../include.php";

$curtain = new Curtain($argv[1]);

if (Curtain::getRsMotorActivity($argv[1])) $curtain->getPercent();
