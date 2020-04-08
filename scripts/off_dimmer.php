<?php
/**
 * Скрипт выключает димер
 * Аргумент - это id объекта диммера, который автоматически подставляется 
 * при вызове метода этого диммера
 */
require_once '../include.php';

$dimmer = new Dimmer($argv[1]);

$dimmer->setValue(0);

//Отображение у объекта приводим в состояние "выключено"

$object = new Objects();

$object->select($argv[1]);
$object->setStatus('OFF');