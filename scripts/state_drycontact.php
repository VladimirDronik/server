<?php
/**
 * Системный скрипт. Берет состояние порта, на котором находится объект и присваивает его объекту
 */

require_once '../include.php';

$drycontact = new Objects();
$drycontact->select($argv[1]);

//Получаем состояние порта, на котором висит данный элемент
$status = $drycontact->getPortState();

//Присваиваем объекту это состояние
$drycontact->setStatus($status,true, false);