<?php
require_once '../include.php';

$sensors = [];

$sql = System::$db->query(
    "SELECT `id` FROM `objects` WHERE `type` = 'sensor'"
);

if($sql->rowCount() > 0) {
    $sensorsIdArray = $sql->fetchAll(PDO::FETCH_COLUMN);

    foreach ($sensorsIdArray as $sId)
    {
        if (null !== $sensor = new Sensor($sId)) {
            if ($sensor->device->source != 'mqtt')
            {
                $sensor->checkSensor();
                $sensor->launchRegulator();
            }
        }
    }
}