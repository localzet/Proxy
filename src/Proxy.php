<?php

namespace localzet;

class Proxy extends Server
{
    /**
     * @inheritdoc
     */
    private string $name = 'Localzet Proxy';

    /**
     * @inheritdoc
     */
    private int $count = 6;

    /**
     * @inheritdoc
     */
    private $onMessage = null;

    /**
     * @inheritdoc
     */
    public function __construct(string $socketName = null, array $socketContext = [])
    {
        parent::__construct($socketName, $socketContext);
        $this->onMessage = function ($connection, $buffer) {
            list($method, $addr,) = explode(' ', $buffer);
            $url_data = parse_url($addr);

            if (!isset($url_data['host'])) {
                // Обработка ошибки
                return;
            }

            $host = $url_data['host'];
            $port = $url_data['port'] ?? 80;
            $addr = "$host:$port";

            $remote_connection = new Server\Connection\AsyncTcpConnection("tcp://$addr");

            if ($method === 'CONNECT') {
                $connection->send("HTTP/1.1 200 Connection Established\r\n\r\n");
            } else {
                $remote_connection->send($buffer);
            }

            $remote_connection->pipe($connection);
            $connection->pipe($remote_connection);

            $remote_connection->onError = function($connection, $code, $message) {
                // Обработка ошибки
            };

            $remote_connection->connect();
        };
    }
}
