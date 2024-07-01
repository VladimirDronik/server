<?php

require_once '../include.php';

Modbus::modbusRtu($argv[1], 'write', 0, $argv[2]);