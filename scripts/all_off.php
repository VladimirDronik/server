<?php
/**
 * Скрипт выключает всю нагрузку в доме, которая не нужна например ночью или когда никого нет дома.
 */


require_once '../../include.php';


$object = new Objects();


$object->select(20);
$object->set_status('off');

$object->select(21);
$object->set_status('off');

$object->select(22);
$object->set_status('off');

$object->select(23);
$object->set_status('off');

$object->select(24);
$object->set_status('off');

$object->select(25);
$object->set_status('off');

$object->select(26);
$object->set_status('off');

$object->select(27);
$object->set_status('off');

$object->select(28);
$object->set_status('off');

$object->select(31);
$object->set_status('off');

$object->select(32);
$object->set_status('off');

$object->select(33);
$object->set_status('off');

$object->select(34);
$object->set_status('off');

$object->select(35);
$object->set_status('off');

$object->select(36);
$object->set_status('off');

$object->select(37);
$object->set_status('off');

$object->select(38);
$object->set_status('off');

$object->select(39);
$object->set_status('off');

//Отжимаем кнопку
$object->select(42);
$object->set_status('off',true,false);