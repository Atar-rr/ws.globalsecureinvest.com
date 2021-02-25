<?php

use WebSocket\Client;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;

require_once __DIR__ . '/vendor/autoload.php';

$httpClient = new \GuzzleHttp\Client();

//получаем данные о том, какие акции нужно получать
try {
    $httpClient = new \GuzzleHttp\Client();
    $data = $httpClient->request('GET', '');
    $symbols = json_decode($data, true);
} catch (\Exception $e) {
    #TODO дополнить
    $symbols = ['AAPL', 'FB', 'TSLA', 'PLTR', 'AMZN', 'ATVI', 'MSFT'];
}

$context = [
    'ssl' => [
        'local_cert'=> __DIR__ . '/cert.pem',
        'local_pk' =>  __DIR__ . '/key.pem',
        'verify_peer'  => false,
    ]
];

//включаем режим демона
//\Workerman\Worker::$daemonize=true;

//создаем WebSocket
$webSocketWorker = new \Workerman\Worker('websocket://0.0.0.0:2346', $context);
$webSocketWorker->count = 4;
//$webSocketWorker->transport = 'ssl';


$client = new Client("wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50");
$client->setFragmentSize(30000);
$client->setTimeout(100);

$webSocketWorker->onConnect = function ($connection) use ($client, $symbols) {
    echo "New connection\n";
    foreach ($symbols as $symbol) {
        $client->text(json_encode(['type' => 'subscribe', 'symbol' => $symbol]));
    }

    TcpConnection::$defaultMaxSendBufferSize = 10485760;
};

$webSocketWorker->onMessage = function ($connection, $message) use ($webSocketWorker, $client) {

    $time_interval = 1.5;
    Timer::add($time_interval, function() use ($connection, $client, $message)
    {
        $result = [];
        $resultFinhub = json_decode($client->receive(), true);

        $returnSymb = json_decode($message, true);
        if ($message !== null && !empty($resultFinhub)) {
            $result = array_filter($resultFinhub['data'], function ($item) use ($returnSymb) {
                return in_array($item['s'], $returnSymb);
            });
        }

        $connection->send($message !== null ? json_encode($result, JSON_UNESCAPED_UNICODE) : json_encode($resultFinhub, JSON_UNESCAPED_UNICODE));
    });
};

$webSocketWorker->onClose = function () use ($client, $symbols) {
    foreach ($symbols as $symbol) {
        $client->text(json_encode(['type' => 'unsubscribe', 'symbol' => $symbol]));
    }

    echo "Connection closed\n";
};

$webSocketWorker->onWorkerStop = function () use ($client, $symbols) {
    foreach ($symbols as $symbol) {
        $client->text(json_encode(['type' => 'unsubscribe', 'symbol' => $symbol]));
    }
};

\Workerman\Worker::runAll();
