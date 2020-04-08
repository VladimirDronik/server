<?php
/**
 * Скрипт включает димер на предыдущем установленном значении
 * Аргумент - это id объекта диммера, который автоматически подставляется 
 * при вызове метода этого диммера
 */
require_once '../include.php';

$dimmer = new Dimmer($argv[1]);

//Включаем последний режим у диммера
    $dimmer->setValue($dimmer->getOldValue());

//Отображение у объекта приводим в состояние "включено"
$object = new Objects();

$object->select($argv[1]);
$object->setStatus('ON');
