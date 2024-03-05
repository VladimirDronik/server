<?php

include_once "../include.php";

$device = "/dev/ttyUSB" . $argv[1] - 1;

$sql = System::$db->query(" SELECT `modbus_buses`.`id`
                            FROM `modbus_buses`
                            WHERE `modbus_buses`.`device` = '$device'");
        
if ($sql->rowCount() > 0)
{
    $busId = $sql->fetch(PDO::FETCH_OBJ)->id;
    Modbus::pollingLoop (0, $busId);
}
else exit(6);