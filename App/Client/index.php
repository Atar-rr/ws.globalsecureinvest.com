<?php

use App\Client\ClientWorker;

require_once __DIR__ . '/../../vendor/autoload.php';

$clientWorker = new ClientWorker();
$clientWorker->run();

//
//const FINHUB_URI = 'wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50';
//const SUBSCRIBE = 'subscribe';
//const FIELD_SYMBOL = 'symbol';
//const FIELD_TYPE = 'type';
//const START = 'start';
//const STOP = 'stop';
//const DAEMON_COMMANDS = [START, STOP];
//
//global $argv;
//
//$command = '';
//$uniqPrefix = str_replace('/', '_', getenv()['PWD']);
//$pidFile = $uniqPrefix . ".pid";
//$logFile = $uniqPrefix . ".log";
//
//
//foreach ($argv as $item) {
//    if (in_array($item, DAEMON_COMMANDS)) {
//        $command = $item;
//    }
//}
//
//$masterPid = is_file(__DIR__ . "/{$pidFile}") ? file_get_contents(__DIR__ . "/{$pidFile}") : 0;
//if ($command === START) {
//    $masterIsAlive = $masterPid && posix_kill($masterPid, 0) && \posix_getpid() !== $masterPid;
//
//    if ($masterIsAlive) {
//        echo "Процесс уже запущен\n";
//        exit();
//    }
//
//    $childPid = pcntl_fork();
//    if ($childPid > 0) {
//        exit();
//    }
//
//    posix_setsid();
//    file_put_contents(__DIR__ . '/' . $pidFile, posix_getpid());
//    while (true) {
//        daemon();
//    }
//    //демон
//} else if ($command === STOP) {
//    $sig = SIGINT;
//    $masterPid && \posix_kill($masterPid, $sig);
//    usleep(10000);
//} else {
//    echo "Доступны команды [start | stop]\n";
//    exit();
//}
//
//function daemon()
//{
//    $client = new WebSocket\Client(FINHUB_URI);
//    $client->setTimeout(5);
//
//    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
//    $channel = $connection->channel();
//    $channel->queue_declare('finhub', false, false, false, false);
//
////запрашиваем данные о том, какие акции нужно получать
//    try {
//        $httpClient = new Client();
//        $request = $httpClient->request('GET', 'https://globalsecureinvest.com/wp-json/wp/v2/symbols');
//        $symbols = json_decode($request->getBody()->getContents(), true);
//    } catch (\Exception $e) {
//
//    }
//    $symbols [] = 'BINANCE:BTCUSDT';
//
//    foreach ($symbols as $symbol) {
//        usleep(10000);
//        var_dump($symbols);
//        $client->text(json_encode([FIELD_TYPE => SUBSCRIBE, FIELD_SYMBOL => $symbol]));
//    }
//
//    while (true) {
//        try {
//            usleep(500000);
//            $result = $client->receive();
//            $msg = new AMQPMessage($result);
//            $channel->basic_publish($msg, '', 'finhub');
//        } catch (\Exception $e) {
//            log($e->getMessage());
//        }
//    }
//}