<?php
/**
 * Скрипт запускается при срабатывании датчика движения
 */


require_once '../../include.php';

$cnt_light = 10; //интервал включения освещения, сек.


$script = new Scripts();


//Если сейчас день
$now = strtotime(date('H:i'));
$start = strtotime(date('17:00'));
$end = strtotime(date('10:00'));


if (($now>$start)||($now<$end)) {

$st = $script->status(11,'get',1);
$status = explode('/',$st);
//Включаем свет 
if ($status[0]=='ON'){
$script->set(27,1, 1);


	while ($cnt<$cnt_light){
	
	$st = $script->status(11,'get',1);
	$status = explode('/',$st);	
	
	if ($status[0]=='ON') $cnt=0;

	sleep(1);

	$cnt++;

	}

$script->set(27,0, 1);
}

}





