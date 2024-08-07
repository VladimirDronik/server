<?php

include_once "../include.php";

// $queue = new ModbusQueue($argv[1]);
// $queue->runClient();
Rs485::queue($argv[1]);