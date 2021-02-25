<?php

use GuzzleHttp\Client;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use WSSC\Components\ClientConfig;
use WSSC\WebSocketClient;

require_once __DIR__ . '/vendor/autoload.php';

const FINHUB_URI = 'wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50';
const SOCKET_NAME = 'websocket://45.90.33.104:2346';

const SUBSCRIBE = 'subscribe';
const UNSUBSCRIBE = 'unsubscribe';

const FIELD_SYMBOL = 'symbol';
const FIELD_TYPE   = 'type';
const FIELD_DATA   = 'data';
const FIELD_S      = 's';

$httpClient = new Client();

//запрашиваем данные о том, какие акции нужно получать
try {
    $httpClient = new Client();
    $data = $httpClient->request('GET', '');
    $symbols = json_decode($data, true);
} catch (\Exception $e) {
    $symbols = ['AAPL', 'FB', 'TSLA', 'PLTR', 'AMZN', 'ATVI', 'MSFT'];
}

$context = [
    'ssl' => [
        'local_cert'=> '/etc/letsencrypt/live/ws.globalsecureinvest.com/cert.pem',
        'local_pk' =>  '/etc/letsencrypt/live/ws.globalsecureinvest.com/privkey.pem',
        'verify_peer'  => false,
    ]
];

//включаем режим демона
\Workerman\Worker::$daemonize=true;

//создаем WebSocket
$webSocketWorker = new \Workerman\Worker(SOCKET_NAME, $context);
$webSocketWorker->transport = 'ssl';

$config = new ClientConfig();
$config->setFragmentSize(20000);
$config->setTimeout(50);

$client = new WebSocketClient(FINHUB_URI, $config);

$webSocketWorker->onConnect = function ($connection) use ($client, $symbols) {
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

$webSocketWorker->onClose = function () use ($client, $symbols) {
    echo "Connection closed\n";
};

$webSocketWorker->onWorkerStart = function () use ($client, $symbols) {
    foreach ($symbols as $symbol) {
        $client->send(json_encode([FIELD_TYPE => SUBSCRIBE, FIELD_SYMBOL => $symbol]));
    }

    TcpConnection::$defaultMaxSendBufferSize = 10485760;
};

$webSocketWorker->onWorkerStop = function () use ($client, $symbols) {
    foreach ($symbols as $symbol) {
        $client->send(json_encode([FIELD_TYPE => UNSUBSCRIBE, FIELD_SYMBOL => $symbol]));
    }
};

\Workerman\Worker::runAll();
