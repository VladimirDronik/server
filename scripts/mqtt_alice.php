<?php
require_once '../include.php';

$mqtt = new Mqtt();
$mqtt->subscribe('alice/#');