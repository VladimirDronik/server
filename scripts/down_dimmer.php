<?php
/**
 * Уменьшение значения диммера
 */

require_once '../include.php';

$dimmer = new Dimmer($argv[1]);


if($argv[2] == 2)
    $dimmer->setEasy('v');
else
    $dimmer->setEasy('x');