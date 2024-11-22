<?php

use PhpMqtt\Client\MqttClient;

class Mqtt extends System
{
    private $client;

    public function __construct() {
        $this->client = $client = new MqttClient(MQTT_HOST, MQTT_PORT);
    }

    public function connect() {
        $this->client->connect(null, true);
    }
    
    public function subscribeRs485(string $topic, $uid) {
        $this->connect();
        $c = $this->client;
        $this->client->subscribe($topic, function ($topic, $message) use ($uid, $c, &$response) {
            $response = json_decode($message, true);
            if ($uid == $response['uid']) $c->interrupt();
        }, MqttClient::QOS_AT_MOST_ONCE);
        $this->client->loop(true);
        $this->disconnect();
        return $response;
    }

    public function disconnect() {
         $this->client->disconnect();
    }

    public function publish(string $topic, array $payload, $retained = true, $qos = MqttClient::QOS_AT_MOST_ONCE) {
        $this->connect();
        $this->client->publish($topic, json_encode($payload), $qos, $retained);
        $this->disconnect(); 
    }


    public function subscribe(string $topic) {
        $this->connect();
        $c = $this->client;
        $this->client->subscribe($topic, function ($topic, $message) use ($c) {
            $payload = json_decode($message, true);
            if ($topic == 'alice/callback') {
                Device::aliceCallbackState($payload['object_id'], $payload['capabilities'], $payload['properties']);
            }
        }, MqttClient::QOS_AT_MOST_ONCE);
        $this->client->loop(true);
    }

}