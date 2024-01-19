<?php

require_once '../include.php';

Modbus::putTaskIntoQueue($argv[1], 'write', 0, $argv[2]);