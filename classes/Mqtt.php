<?php

use PhpMqtt\Client\MqttClient;

class Mqtt extends System
{
    const MQTT_HOST = 'localhost';
    const MQTT_PORT = 1883;

    static $response = null;
    static $client;

    public static function connectRs485() {
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
        self::$client = $client;
        // echo (new datetime())->format('Y-m-d H:i:s.v') . ":   Connected" . PHP_EOL;
    }

    public static function subscribeRs485(string $topic, string $uid)
    {
        $client = self::$client;
        
        $client->subscribe($topic, function ($topic, $message) use ($client, $uid) {
            // echo (new datetime())->format('Y-m-d H:i:s.v') . ":   Subscribed" . PHP_EOL;
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
        // echo (new datetime())->format('Y-m-d H:i:s.v') . ":   Published" . PHP_EOL;
    }
    
}