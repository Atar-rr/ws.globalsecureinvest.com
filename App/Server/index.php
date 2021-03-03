<?php

use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once __DIR__ . '/../../vendor/autoload.php';

const SOCKET_NAME = 'websocket://45.90.33.104:2346';

$context = [
    'ssl' => [
        'local_cert'=> '/etc/letsencrypt/live/ws.globalsecureinvest.com/cert.pem',
        'local_pk' =>  '/etc/letsencrypt/live/ws.globalsecureinvest.com/privkey.pem',
        'verify_peer'  => false,
    ]
];

//включаем режим демона
Worker::$daemonize=true;

//создаем WebSocket
$webSocketWorker = new Worker(SOCKET_NAME, $context);
$webSocketWorker->count = 1;
$webSocketWorker->transport = 'ssl';

$webSocketWorker->onConnect = function ($connection) {
    echo "New connection\n";
};

$webSocketWorker->onMessage = function ($connection, $message) {
};

$webSocketWorker->onClose = function ($connection) {
    echo "Connection closed\n";
};

$webSocketWorker->onError = function ($connection) {
    echo "Connection error\n";
};

$webSocketWorker->onWorkerStart = function () use ($webSocketWorker) {
    echo "Worker start\n";

    TcpConnection::$defaultMaxSendBufferSize = 10485760;
    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();
    $channel->queue_declare('finhub', false, false, false, false);
    $time_interval = 0.7;
    $data = '';

    Timer::add($time_interval, function () use ($webSocketWorker, $channel, &$data) {
	    $callback = function ($msg) use (&$data) {
            $data = $msg->body;
            $msg->ack();
        };
        $channel->basic_consume('finhub', '', false, false, false, false, $callback);

        $channel->wait(null, true);
        foreach ($webSocketWorker->connections as $connection) {
            $connection->send(is_string($data) ? $data : json_encode([]));
        }
        $data = json_encode([]);
    });
};

$webSocketWorker->onWorkerStop = function () {
    echo "Worker stop\n";
};

Worker::runAll();
