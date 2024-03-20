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
        $dimmer->setValue(0);
        sleep ($seconds);
        $dimmer->setValue($dimmer->getValue());
        break;
    case 'virtal':
        $object->setStatus('off', true, false);
        sleep ($seconds);
        $object->setStatus('on', true, false);
        break;
    default:
        $object->setStatus('off', true, true);
        sleep ($seconds);
        $object->setStatus('on', true, true);
        break;
}

System::deleteVariable("timerForObjectId$idObject");