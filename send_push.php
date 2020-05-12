#!/usr/bin/env php
<?php

$url = 'https://fcm.googleapis.com/fcm/send';
$YOUR_API_KEY = 'AAAAVkO1aus:APA91bHWQW70VnN3hGEgbFGCqNOCsmq39RSvnPCkqs1sZRLbqg2tZvhn3S8ZhSSvthrWtYtLEbiF9GQMGyaCWt8gh5coWU7-zQjKBtdLtxfHEY7AE5AEgzR7oh3lh7PppwmeDQTVa41x'; // Server key
$YOUR_TOKEN_ID = $argv[0]; // Client token id
$title = $argv[1];
$body = $argv[2];

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

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
curl_close($ch);
