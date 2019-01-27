<?php
/**
 * Скрипт выключает всю нагрузку в доме, которая не нужна например ночью или когда никого нет дома.
 */


require_once '../../include.php';


$script = new Scripts();

file_get_contents("http://192.168.88.14/sec/?cmd=a:0");
