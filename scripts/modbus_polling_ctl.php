<?php

require_once '../include.php';

$pollingCycle = (isset($argv[3]) ? $argv[3] : null);
Modbus::pollingCtl($argv[1], $argv[2], $pollingCycle);