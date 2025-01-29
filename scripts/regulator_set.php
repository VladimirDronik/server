<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);

if (null !== $regulator = new Regulator($id)) {
    if ($regulator->updateRegulator()) exit(0);
}
        
exit(1);