<?php

include_once "../include.php";

use PhpMqtt\Client\MqttClient;

$panel_id = $argv[1];
MqttPanel::subscribe_switch($argv[1]);
