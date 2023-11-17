<?php declare(strict_types=1);

/**
 * @package     Localzet Server
 * @link        https://github.com/localzet/Server
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2018-2023 Localzet Group
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *              For any questions, please contact <creator@localzet.com>
 */

use localzet\Server;
use localzet\Server\Connection\AsyncTcpConnection;

// Autoload.
require_once __DIR__ . '/vendor/autoload.php';

// Create a TCP worker.
$worker = new Server('tcp://0.0.0.0:8080');
// 6 processes
$worker->count = 6;
// Worker name.
$worker->name = 'php-http-proxy';

// Emitted when data received from client.
$worker->onMessage = function($connection, $buffer)
{
    // Parse http header.
    list($method, $addr, $http_version) = explode(' ', $buffer);
    $url_data = parse_url($addr);
    $addr = !isset($url_data['port']) ? "{$url_data['host']}:80" : "{$url_data['host']}:{$url_data['port']}";
    // Async TCP connection.
    $remote_connection = new AsyncTcpConnection("tcp://$addr");
    // CONNECT.
    if ($method !== 'CONNECT') {
        $remote_connection->send($buffer);
    // POST GET PUT DELETE etc.
    } else {
        $connection->send("HTTP/1.1 200 Connection Established\r\n\r\n");
    }
    // Pipe.
    $remote_connection ->pipe($connection);
    $connection->pipe($remote_connection);
    $remote_connection->connect();
};

// Run.
Server::runAll();