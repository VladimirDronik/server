<?php
/**
 * Увеличение значения диммера
 */

require_once '../include.php';

$dimmer = new Dimmer($argv[1]);


if($argv[2] == 2)
    $dimmer->setEasy('^');
else
    $dimmer->setEasy('x');
