<?php

require_once __DIR__ . '/vendor/autoload.php';

$webSocketWorker = new \Workerman\Worker('websocket://0.0.0.0:2346');
$webSocketWorker->count = 1;
$client = new WebSocket\Client("wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50");

$webSocketWorker->onConnect = function ($connection) {
    echo "New connection\n";
};

$webSocketWorker->onMessage = function ($connection, $data) use ($webSocketWorker, $client) {
    $client->text(json_encode([
        ['type' => 'subscribe', 'symbol' => 'AAPL'],
        ['type' => 'subscribe', 'symbol' => 'AMZN'],
        ['type' => 'subscribe', 'symbol' => 'MSFT'],
        ['type' => 'subscribe', 'symbol' => 'GTHX'],
        ['type' => 'subscribe', 'symbol' => 'PLTR'],
        ['type' => 'subscribe', 'symbol' => 'ATVI'],
        ['type' => 'subscribe', 'symbol' => 'EA'],
        ['type' => 'subscribe', 'symbol' => 'PYPL'],
        ['type' => 'subscribe', 'symbol' => 'CRM'],
        ['type' => 'subscribe', 'symbol' => 'TTWO'],
        ['type' => 'subscribe', 'symbol' => 'ADBE'],
        ['type' => 'subscribe', 'symbol' => 'NFLX'],
        ['type' => 'subscribe', 'symbol' => 'GOOG'],
        ['type' => 'subscribe', 'symbol' => 'TSLA'],
        ['type' => 'subscribe', 'symbol' => 'FB'],
    ]));
    $connection->send($client->receive());
};

$webSocketWorker->onClose = function ($connection) {
    echo "Connection closed\n";
};

\Workerman\Worker::runAll();