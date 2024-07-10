<?php

include_once "../include.php";

$id = (isset($argv[1]) ? $argv[1] : null);
$ac = new Conditioner($id);
$ac->updateAcParams();