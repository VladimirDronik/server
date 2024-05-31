<?php

include_once "../include.php";

$ac = new Conditioner($argv[1]);

$ac->updateAcParams();