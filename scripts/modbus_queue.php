<?php

include_once "../include.php";

$device = "/dev/ttyUSB" . $argv[1] - 1;

$sql = System::$db->query(" SELECT `modbus_buses`.`id`
                            FROM `modbus_buses`
                            WHERE `modbus_buses`.`device` = '$device'");
        
if ($sql->rowCount() > 0)
{
    $busId = $sql->fetch(PDO::FETCH_OBJ)->id;
    $queue = new ModbusQueue($busId);
    $queue->runClient();
}
else exit(6);