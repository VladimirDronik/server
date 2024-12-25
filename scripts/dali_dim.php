<?php

require_once '../include.php';
if($argv[2] == 'odd') $tb = 100;
if($argv[2] == 'even') $tb = 0;
$dali = new Dali($argv[1]);
$dali->onWithFade($tb);