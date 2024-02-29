<?php

require_once '../include.php';

// $object = new Objects ();
// $object->select(6);
// $object->setStatus('on', true, true);
// sleep (15);
// $object->setStatus('off', true, true);
// var_dump ($object);

// $object->select(7);
// var_dump ($object);

// Action::runAction(8, 'device', 8, null, 'c');
// Action::runAction(16, 'device', 13, null, 'c');
// Action::runAction(27, 'device', 8, 2, 'lc');
// Action::runWithoutMethod(19);
// Action::runAction(18, null, 12, null, false);

$ipAddress = '10.200.3.11';
$object = new Objects();
$objectsWithStatus = [];

$sql = System::$db->query(" SELECT `ports`.`object`, `ports`.`status`, `ports`.`num_port`, `objects`.`status`
                            FROM `ports` 
                            INNER JOIN `devices` ON `devices`.`id`=`ports`.`id_device`
                            INNER JOIN `objects` ON `objects`.`id` = `ports`.`object`
                            WHERE `ports`.`object` IS NOT NULL
                            AND `devices`.`ip_address` = '$ipAddress'
                            AND LOWER(`ports`.`status`) = 'out'");

while ($outPortObject = $sql->fetch(PDO::FETCH_OBJ))
{
    $objectsWithStatus [$outPortObject->object] = $outPortObject->status;
}

foreach ($objectsWithStatus as $objectId => $objectStatus)
{
    $object->select($objectId);
    $object->setStatus($objectStatus, true, true);
}
