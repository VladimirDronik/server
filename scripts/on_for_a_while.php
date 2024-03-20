<?php
/**
 * Скрипт включает объект на время
 */
require_once '../include.php';

$idObject = $argv[1];
$seconds = $argv[2];

$object = new Objects ();
$object->select($idObject);

System::setVariable("timerForObjectId$idObject", getmypid());

switch ($object->type)
{
    case 'dimmer':
        $dimmer = new Dimmer($idObject);
        $dimmer->setValue($dimmer->getValue());
        sleep ($seconds);
        $dimmer->setValue(0);
        break;
    case 'virtal':
        $object->setStatus('on', true, false);
        sleep ($seconds);
        $object->setStatus('off', true, false);
        break;
    default:
        $object->setStatus('on', true, true);
        sleep ($seconds);
        $object->setStatus('off', true, true);
        break;
}

System::deleteVariable("timerForObjectId$idObject");