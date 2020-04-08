<?php
/**
 * Переключает состояние диммера. Если диммер был выключен, значит включает его на последнем значении.
 * Если диммер был включен, значит выкллючает его
 */

require_once '../include.php';

$dimmer = new Dimmer($argv[1]);
$object = new Objects();

$object->select($argv[1]);


if($dimmer->getValue() == 0) {
    $dimmer->setValue($dimmer->getOldValue());
    $object->setStatus('ON');
}
else {
    $dimmer->setValue(0);
    $object->setStatus('OFF');
}