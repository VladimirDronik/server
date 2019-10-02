<?php
/**
 * Скрипт выключает всю нагрузку в доме, которая не нужна например ночью или когда никого нет дома.
 */


require_once '../../include.php';


$object = new Objects();


$object->select(20);
$object->setStatus('off');

$object->select(21);
$object->setStatus('off');

$object->select(22);
$object->setStatus('off');

$object->select(23);
$object->setStatus('off');

$object->select(24);
$object->setStatus('off');

$object->select(25);
$object->setStatus('off');

$object->select(26);
$object->setStatus('off');

$object->select(27);
$object->setStatus('off');

$object->select(28);
$object->setStatus('off');

$object->select(31);
$object->setStatus('off');

$object->select(32);
$object->setStatus('off');

$object->select(33);
$object->setStatus('off');

$object->select(34);
$object->setStatus('off');

$object->select(35);
$object->setStatus('off');

$object->select(36);
$object->setStatus('off');

$object->select(37);
$object->setStatus('off');

$object->select(38);
$object->setStatus('off');

$object->select(39);
$object->setStatus('off');

//Отжимаем кнопку
$object->select(42);
$object->setStatus('off',true,false);