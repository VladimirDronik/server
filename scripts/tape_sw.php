<?php

require_once '../include.php';

// $argv[1] - id объекта
$tape = new Tape($argv[1]);
$tape->tapeSw();
