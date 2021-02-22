<?php

require_once __DIR__ . '/vendor/autoload.php';

\Workerman\Worker::$daemonize=true;
$webSocketWorker = new \Workerman\Worker('websocket://0.0.0.0:2346');
$webSocketWorker->count = 1;
$client = new WebSocket\Client("wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50");

$webSocketWorker->onConnect = function ($connection) use ($client) {
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'AAPL']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'AMZN']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'MSFT']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'ATVI']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'GTHX']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'PLTR']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'EA']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'PYPL']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'CRM']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'TTWO']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'ADBE']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'NFLX']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'GOOG']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'TSLA']));
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'FB']));

    echo "New connection\n";
};
$webSocketWorker->onMessage = function ($connection, $data) use ($webSocketWorker, $client) {
    $connection->send($client->receive());
};

$webSocketWorker->onClose = function ($connection) {
    echo "Connection closed\n";
};

\Workerman\Worker::runAll();