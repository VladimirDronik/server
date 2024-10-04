<?php

include_once "../include.php";

// $queue = new ModbusQueue($argv[1]);
// $queue->runClient();
$queue = new Rs485($argv[1]);
$queue->queue();