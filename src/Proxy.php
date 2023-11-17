<?php

namespace localzet;

class Proxy extends Server
{
    /**
     * @inheritdoc
     */
    public string $name = 'Localzet Proxy';

    /**
     * @inheritdoc
     */
    public int $count = 6;

    /**
     * @inheritdoc
     */
    public $onMessage = null;

    /**
     * @inheritdoc
     */
    public function __construct(string $socketName = null, array $socketContext = [])
    {
        parent::__construct($socketName, $socketContext);
        $this->onMessage = function ($connection, $buffer) {
            list($method, $addr,) = explode(' ', $buffer);
            $url_data = parse_url($addr);
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
            $remote_connection->connect();
        };
    }
}