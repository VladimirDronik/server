<?php

require_once '../include.php';

// $argv[1] - id объекта
$id = (isset($argv[1]) ? $argv[1] : null);
$h = (isset($argv[2]) ? $argv[2] : null);
$s = (isset($argv[3]) ? $argv[3] : null);
$tape = new Tape($id);
$tape->tapeSetColor($h, $s);
