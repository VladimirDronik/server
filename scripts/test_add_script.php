<?php
//тестовый скрипт для всякой всячины
 
require_once '../include.php';
 
$object = new Objects();
$mega = new MegaD();
 
$ports = array(7, 8, 9, 10, 11, 12, 13, 22, 23, 24, 25, 26, 27, 28);
 
foreach($ports as $port) {
$mega->set($port, 1, 13);
sleep(1);
}
 
foreach($ports as $port) {
$mega->set($port, 0, 13);
sleep(1);
}
 
 
$object->select(73);
$object->setStatus('off');