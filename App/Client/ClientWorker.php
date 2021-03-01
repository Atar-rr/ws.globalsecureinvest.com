<?php

namespace App\Client;

use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

class ClientWorker
{
    const FINHUB_URI = 'wss://ws.finnhub.io?token=c0nakvf48v6v9lphti50';
    const SYMBOLS_URI = 'https://globalsecureinvest.com/wp-json/wp/v2/symbols';
    const SUBSCRIBE = 'subscribe';
    const FIELD_SYMBOL = 'symbol';
    const FIELD_TYPE = 'type';
    const QUEUE_FINHUB = 'finhub';
    const START = 'start';
    const STOP = 'stop';
    const DAEMON_COMMANDS = [self::START, self::STOP];

    protected $pidFile = '';

    protected $logFile = '';

    protected $command = '';

    protected $channel;

    protected $masterPid = 0;

    public function __construct()
    {
        $uniqPrefix = str_replace('/', '_', getenv()['PWD']);
        $this->pidFile = $uniqPrefix . ".pid";
        $this->logFile = $uniqPrefix . ".log";
        $this->masterPid = is_file(__DIR__ . "/{$this->pidFile}") ?
            file_get_contents(__DIR__ . "/{$this->pidFile}") : 0;
    }

    public function run()
    {
        $this->checkCommand();
        $this->daemonize();
        $this->worker();
    }

    private function checkCommand()
    {
        global $argv;

        foreach ($argv as $item) {
            if (in_array($item, self::DAEMON_COMMANDS)) {
                $this->command = $item;
            }
        }

        if ($this->command === self::START) {
            $masterIsAlive = $this->masterPid && posix_kill($this->masterPid, 0) && \posix_getpid() !== $this->masterPid;

            if ($masterIsAlive) {
                $msg = "Процесс уже запущен\n";
                echo $msg;
                $this->log($msg);
                exit();
            }

        } else if ($this->command === self::STOP) {
            $sig = \SIGINT;
            $this->masterPid && \posix_kill($this->masterPid, $sig);
            usleep(10000);
            exit();
        } else if ($this->command === '') {
            echo "Доступны команды [start | stop]\n";
            exit();
        }
    }

    protected function daemonize()
    {
        $childPid = pcntl_fork();
        if ($childPid > 0) {
            exit();
        }

        posix_setsid();
        file_put_contents(__DIR__ . '/' . $this->pidFile, posix_getpid());
    }

    protected function worker()
    {
        $client = new \WebSocket\Client(self::FINHUB_URI);
        $client->setTimeout(5);

        $symbols = $this->getSymbols();
        $symbols[] = 'BINANCE:BTCUSDT'; #TODO убрать

        foreach ($symbols as $symbol) {
            usleep(10000);
            $client->text(json_encode([self::FIELD_TYPE => self::SUBSCRIBE, self::FIELD_SYMBOL => $symbol]));
        }

        $this->startRabbit();
        while (true) {
            try {
                usleep(500000);
                $result = $client->receive();
                $msg = new AMQPMessage($result);
                $this->channel->basic_publish($msg, '', self::QUEUE_FINHUB);
            } catch (\Exception $e) {
            }
        }
    }

    protected function getSymbols(): array
    {
        try {
            $httpClient = new Client();
            $request = $httpClient->request('GET', self::SYMBOLS_URI);
            $symbols = json_decode($request->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            $this->log($e->getMessage());
        }

        return $symbols ?? [];
    }

    protected function startRabbit()
    {
        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $this->channel = $connection->channel();
        $this->channel->queue_declare(self::QUEUE_FINHUB, false, false, false, false);
    }

    protected function log($message)
    {
        $message .= "\n";
        $date = date('Y-m-d H:i:s');
        file_put_contents(__DIR__ . '/' . $this->logFile, $date . ' '. $message , LOCK_EX | FILE_APPEND);
    }
}