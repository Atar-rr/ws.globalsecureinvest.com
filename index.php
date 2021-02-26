<?php

use GuzzleHttp\Client;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

const FINHUB_URI = 'wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50';
const SOCKET_NAME = 'websocket://45.90.33.104:2346';

const SUBSCRIBE = 'subscribe';
const UNSUBSCRIBE = 'unsubscribe';

const FIELD_SYMBOL = 'symbol';
const FIELD_TYPE   = 'type';
const FIELD_DATA   = 'data';
const FIELD_S      = 's';

$context = [
    'ssl' => [
        'local_cert'=> '/etc/letsencrypt/live/ws.globalsecureinvest.com/cert.pem',
        'local_pk' =>  '/etc/letsencrypt/live/ws.globalsecureinvest.com/privkey.pem',
        'verify_peer'  => false,
    ]
];

$httpClient = new Client();

//включаем режим демона
Worker::$daemonize=true;

//создаем WebSocket
$webSocketWorker = new Worker(SOCKET_NAME, $context);
$webSocketWorker->transport = 'ssl';

$client = new \WebSocket\Client(FINHUB_URI);
$client->setTimeout(40);
$client->setFragmentSize(30000);


$webSocketWorker->onConnect = function ($connection) use ($client) {
    echo "New connection\n";
};

$webSocketWorker->onMessage = function ($connection, $message) use ($webSocketWorker, $client) {
    $time_interval = 1.1;

    Timer::add($time_interval, function() use ($connection, $client, $message)
    {
        $result = [];
        $resultFinhub = json_decode($client->receive(), true);

        $returnSymbols = json_decode($message, true);
        if (!empty($returnSymbols) && !empty($resultFinhub) && isset($resultFinhub[FIELD_DATA])) {
            $result = array_filter($resultFinhub[FIELD_DATA], function ($item) use ($returnSymbols) {
                return in_array($item[FIELD_S], $returnSymbols);
            });
        }

        $connection->send(empty($returnSymbols) === false ? json_encode($result, JSON_UNESCAPED_UNICODE) : json_encode($resultFinhub, JSON_UNESCAPED_UNICODE));
    });
};

$webSocketWorker->onClose = function () use ($client) {
    echo "Connection closed\n";
};

$webSocketWorker->onWorkerStart = function () use ($client, $httpClient) {
    //запрашиваем данные о том, какие акции нужно получать
    try {
        $request = $httpClient->request('GET', 'https://globalsecureinvest.com/wp-json/wp/v2/symbols');
        $symbols = json_decode($request->getBody()->getContents(), true);
    } catch (\Exception $e) {
    }

    foreach ($symbols as $symbol) {
        $client->text(json_encode([FIELD_TYPE => SUBSCRIBE, FIELD_SYMBOL => $symbol]));
    }

    TcpConnection::$defaultMaxSendBufferSize = 10485760;
};

$webSocketWorker->onWorkerStop = function () use ($client, $httpClient) {
    //запрашиваем данные о том, какие акции нужно получать
    try {
        $request = $httpClient->request('GET', 'https://globalsecureinvest.com/wp-json/wp/v2/symbols');
        $symbols = json_decode($request->getBody()->getContents(), true);
    } catch (\Exception $e) {
    }

    foreach ($symbols as $symbol) {
        $client->text(json_encode([FIELD_TYPE => UNSUBSCRIBE, FIELD_SYMBOL => $symbol]));
    }
};

Worker::runAll();
