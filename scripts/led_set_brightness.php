<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);
$br = (isset($argv[2]) ? $argv[2] : null);
$tape = new Tape($id);
$tape->tapeSetBrightness($br);
