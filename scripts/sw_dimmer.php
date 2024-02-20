<?php
/**
 * Переключает состояние диммера. Если диммер был выключен, значит включает его на последнем значении.
 * Если диммер был включен, значит выкллючает его
 */

require_once '../include.php';

$dimmer = new Dimmer($argv[1]);
$object = new Objects();

$object->select($argv[1]);

if($object->status == 'off') $dimmer->setValue($dimmer->getValue());
else $dimmer->setValue(0);
