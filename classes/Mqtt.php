<?php

use PhpMqtt\Client\MqttClient;

class Mqtt extends System {

    const MQTT_HOST = 'localhost';
    const MQTT_PORT = 1883;

    static $response = null;

    // public static function getViaMqtt (int $registerId, int $priority = null)
    // {
    //     $client = new MqttClient('localhost', 1883);

    //     pcntl_async_signals(true);
    //     pcntl_signal(SIGINT, function () use ($client) {
    //         $client->interrupt();
    //     });

    //     $client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) {
    //         if ($elapsedTime >= 40) {
    //             self::$response = [
    //                 'status' => 'Error',
    //                 'response' => 'Response timeout',
    //             ];
    //             $client->interrupt();
    //         }
    //     });

    //     $client->connect(null, true);

    //     $uid = uniqid();
    //     if (!isset($priority)) $priority = 5;
        
        
    //     self::putTaskIntoQueue($registerId, 'read', $priority, null, $uid);

    //     $client->subscribe("modbus/$uid", function ($topic, $message) use ($client) {
    //         echo sprintf("Received message on topic [%s]: %s\n", $topic, $message);
    //         if (json_decode($message, true)['uid'] == $uid)
    //         {
    //             self::$response = [
    //                 'status' => 'OK',
    //                 'response' => json_decode($message, true)['response'],
    //             ];
    //             $client->interrupt();
    //         }
            
    //     }, MqttClient::QOS_AT_MOST_ONCE);
    //     $client->loop(true);
    //     $client->disconnect();

    //     return self::$response;
    // }

    public static function subscribe(string $topic, string $uid)
    {
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () use ($client) {
            $client->interrupt();
        });

        $client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) {
            if ($elapsedTime >= 7) {
                self::$response = [
                    'error' => true,
                    'error_code' => 'Response timeout',
                ];
                $client->interrupt();
            }
        });

        $client->connect(null, true);

        $client->subscribe($topic, function ($topic, $message) use ($client, $uid) {
            // echo sprintf("Received message on topic [%s]: %s\n", $topic, $message);
            if (json_decode($message, true)['uid'] == $uid)
            {
                self::$response = json_decode($message, true);
                $client->interrupt();
            }
        }, MqttClient::QOS_AT_MOST_ONCE);
        $client->loop(true);

        $client->disconnect();

        return self::$response;
    }


    public static function publish(string $topic, array $payload)
    {
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);
        $client->connect(null, true);
        $client->publish($topic, json_encode($payload), 0, false);
        $client->disconnect();
    }
    
}