<?php

require_once __DIR__ . '/vendor/autoload.php';

$webSocketWorker = new \Workerman\Worker('websocket://0.0.0.0:2346');
$webSocketWorker->count = 1;
$client = new WebSocket\Client("wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50");

$webSocketWorker->onConnect = function ($connection) {
    echo "New connection\n";
};

$webSocketWorker->onMessage = function ($connection, $data) use ($webSocketWorker, $client) {
    $client->text(json_encode(['type' => 'subscribe', 'symbol' => 'AAPL']));
    $connection->send($client->receive());
};

$webSocketWorker->onClose = function ($connection) {
    echo "Connection closed\n";
};

\Workerman\Worker::runAll();