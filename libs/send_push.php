#!/usr/bin/env php
<?php

$url = 'https://fcm.googleapis.com/fcm/send';
$YOUR_API_KEY = 'AAAAOXDD7rg:APA91bHXMDFu5sRq7jsjwwLOGK9hCxae6Tfb9ewKtb9Rqywn8Zku19ZtRifBOU2cmwQ5BGuDTxd3jhbX0H3QVjabw5QwuwT3scEhb3M61zf8AHHrGS41OHCRrzQBWI5ZSguQlgwMhwhP'; // Server key
$YOUR_TOKEN_ID = $argv[1]; // Client token id
$title = $argv[2];
$body = $argv[3];

$request_body = [
    'to' => $YOUR_TOKEN_ID,
    'notification' => [
        'title' => $title,
        'body' => $body,
        //'icon' => '',
        //'click_action' => '',
    ],
];
$fields = json_encode($request_body);

$request_headers = [
    'Content-Type: application/json',
    'Authorization: key=' . $YOUR_API_KEY,
];
var_dump($fields);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
curl_close($ch);
