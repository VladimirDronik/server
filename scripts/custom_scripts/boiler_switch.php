<?php
/**
 * Скрипт включает или выключает котел отопления, учитывая входной параметр on или off.
 */


require_once '../../include.php';


$script = new Scripts();


if ($argv[1]=='on')
    $script->set(3,1, 1);
else
    $script->set(3,0, 1);