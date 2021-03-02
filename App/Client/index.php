<?php

use App\Client\ClientWorker;

require_once __DIR__ . '/../../vendor/autoload.php';

$clientWorker = new ClientWorker();
$clientWorker->run();
