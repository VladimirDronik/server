<?php

use PhpMqtt\Client\MqttClient;

class Mqtt extends System
{
    const MQTT_HOST = 'localhost';
    const MQTT_PORT = 1883;

    
    public static function subscribeRs485(string $topic, $uid) {
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);
        $client->connect(null, true);
        $client->subscribe($topic, function ($topic, $message) use ($uid, $client, &$response) {
            $response = json_decode($message, true);
            if ($uid == $response['uid']) $client->interrupt();
        }, MqttClient::QOS_AT_MOST_ONCE);
        $client->loop(true);
        $client->disconnect();
        return $response;
    }

    public static function publish(string $topic, array $payload, $qos = MqttClient::QOS_AT_MOST_ONCE, $retained = true) {
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);
        $client->connect(null, true);
        $client->publish($topic, json_encode($payload), $qos, $retained);
        $client->disconnect(); 
    }


}