<?php

namespace App\Client;

use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

class ClientWorker
{
    protected const FINHUB_URI = '';
    protected const SYMBOLS_URI = 'https://globalsecureinvest.com/wp-json/wp/v2/symbols';
    protected const SUBSCRIBE = 'subscribe';
    protected const FIELD_SYMBOL = 'symbol';
    protected const FIELD_TYPE = 'type';
    protected const QUEUE_FINHUB = 'finhub';
    protected const START = 'start';
    protected const STOP = 'stop';
    protected const DAEMON_COMMANDS = [self::START, self::STOP];

    protected $pidFile = '';

    protected $logFileError = '';

    protected $logFile = 'log.txt';

    protected $command = '';

    protected $channel;

    protected $masterPid = 0;

    public function __construct()
    {
        $uniqPrefix = '_Client_Worker_php';
        $this->pidFile = $uniqPrefix . ".pid";
        $this->logFileError = $uniqPrefix . ".log";
        $this->masterPid = is_file(__DIR__ . "/{$this->pidFile}") ?
            file_get_contents(__DIR__ . "/{$this->pidFile}") : 0;
    }

    public function run(): void
    {
        $this->checkCommand();
        $this->daemonize();
        $this->resetStd();
        $this->worker();
    }

    private function checkCommand(): void
    {
        global $argv;

        foreach ($argv as $item) {
            if (in_array($item, self::DAEMON_COMMANDS, true)) {
                $this->command = $item;
            }
        }

        if ($this->command === self::START) {
            $masterIsAlive = $this->masterPid && posix_kill($this->masterPid, 0) && \posix_getpid() !== $this->masterPid;

            if ($masterIsAlive) {
                $msg = "Процесс уже запущен\n";
                echo $msg;
                $this->log($msg, $this->logFileError);
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

    protected function daemonize(): void
    {
        $childPid = pcntl_fork();
        if ($childPid > 0) {
            exit();
        }

        posix_setsid();
        file_put_contents(__DIR__ . '/' . $this->pidFile, posix_getpid());
    }

    protected function worker(): void
    {
        $client = new \WebSocket\Client(self::FINHUB_URI);
        $client->setTimeout(10);

        $symbols = $this->getSymbols();

        foreach ($symbols as $symbol) {
            usleep(20000);
            $client->text(json_encode([self::FIELD_TYPE => self::SUBSCRIBE, self::FIELD_SYMBOL => $symbol]));
        }

        $this->startRabbit();
        $i = 0;
        $skipSymbols = [];
        while (true) {
            try {
                $result = $client->receive();
                $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                if (!isset($result['data'])) {
                    $this->log('Отсутсвует data в результате', $this->logFile);
                    continue;
                }

                foreach ($result['data'] as $value) {
                    if (in_array($value['s'], $skipSymbols, true)) {
                        continue;
                    }
                    $skipSymbols[] = $value['s'];
                    $msg['data'][] = $value;
                }

                $i++;
                if ($i < 12) {
                    continue;
                }

                $msg['type'] = 'trade';
                $msg = new AMQPMessage(json_encode($msg, JSON_THROW_ON_ERROR));
                $this->log($msg, $this->logFile);
                $this->channel->basic_publish($msg, '', self::QUEUE_FINHUB);

                //сбрасываем переменные
                $msg = [];
                $skipSymbols = [];
                $i = 0;
            } catch (\Exception $e) {
                $i++;
                $this->log($e->getMessage(), $this->logFileError);
                $msg = new AMQPMessage(json_encode([]));
                $this->channel->basic_publish($msg, '', self::QUEUE_FINHUB);
            }
        }
    }

    protected function getSymbols(): array
    {
        try {
            $httpClient = new Client();
            $request = $httpClient->request('GET', self::SYMBOLS_URI);
            $symbols = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->log($e->getMessage(), $this->logFileError);
        }

        return $symbols ?? [];
    }

    protected function startRabbit(): void
    {
        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $this->channel = $connection->channel();
        $this->channel->queue_declare(self::QUEUE_FINHUB, false, false, false, false);
    }

    protected function resetStd(): void
    {
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        fopen('/dev/null', 'rb');
        fopen(__DIR__ . '/application.log', 'ab');
        fopen(__DIR__ . '/daemon.log', 'ab');
    }

    protected function log($message, $logFile): void
    {
        $message .= "\n";
        $date = date('Y-m-d H:i:s');
        file_put_contents(__DIR__ . '/' . $logFile, $date . ' '. var_export($message, true), LOCK_EX | FILE_APPEND);
    }
}
