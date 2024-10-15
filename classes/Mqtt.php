<?php

use PhpMqtt\Client\MqttClient;

class Mqtt extends System
{
    const MQTT_HOST = 'localhost';
    const MQTT_PORT = 1883;

    // static $response = null;


    // public static function connectRs485() {
    //     $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);
        
    //     // pcntl_async_signals(true);
    //     // pcntl_signal(SIGINT, function () use ($client) {
    //     //     $client->interrupt();
    //     // });

        

    //     $client->connect(null, true);
    //     self::$client = $client;
    //     // echo (new datetime())->format('Y-m-d H:i:s.v') . ":   Connected" . PHP_EOL;
    // }

    public static function subscribeRs485(string $topic, $uid) {
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);
        $client->connect(null, true);

        $client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) use (&$response) {
            if ($elapsedTime >= 7) {
                $response = [
                    'error' => true,
                    'error_code' => 'Response timeout',
                ];
                $client->interrupt();
            }
        });

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
        // echo (new datetime())->format('Y-m-d H:i:s.v') . ":   Published" . PHP_EOL;
        $client->disconnect(); 
    }
}