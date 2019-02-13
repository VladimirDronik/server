<?php
/**
 * Скрипт включает или выключает котел отопления, учитывая входной параметр on или off.
 */


require_once '../../include.php';


$script = new Scripts();


if ($argv[1]=='on')
    $script->set(25,0, 1);
else
    $script->set(25,1, 1);