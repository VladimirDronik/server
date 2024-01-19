<?php

require_once '../include.php';

Modbus::putTaskIntoQueue($argv[1], 'read', $argv[2]);