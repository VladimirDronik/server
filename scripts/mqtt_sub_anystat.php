<?php

include_once "../include.php";

use PhpMqtt\Client\MqttClient;


$panel_id = $argv[1];
//$panel_id = 1;

MqttPanel::subscribe_anystat($panel_id);
