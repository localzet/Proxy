<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use localzet\Proxy;
use localzet\Server;

$proxyPort = (int)getenv('PROXY_TEST_PORT');
$targetPort = (int)getenv('PROXY_TEST_TARGET_PORT');
$closedPort = (int)getenv('PROXY_TEST_CLOSED_PORT');

Server::$pidFile = sys_get_temp_dir() . '/proxy-integration-' . getmypid() . '.pid';
Server::$logFile = sys_get_temp_dir() . '/proxy-integration-' . getmypid() . '.log';

$proxy = new Proxy("tcp://127.0.0.1:$proxyPort");
$proxy->count = 1;
$proxy->allowedHosts = ['127.0.0.1'];
$proxy->allowedPorts = [$targetPort, $closedPort];

Server::runAll();
