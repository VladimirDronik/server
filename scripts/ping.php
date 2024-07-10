<?php

require_once '../include.php';

Device::checkAvailible('devices');
Modbus::checkModbusAvailible();
Curtain::checkRsMotorAvailible();
