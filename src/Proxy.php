<?php

/**
 * @package     Localzet Proxy
 * @link        https://github.com/localzet/Proxy
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 *              For any questions, please contact <creator@localzet.com>
*/

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

    /** @var list<string> Exact host names allowed for outbound connections. */
    public array $allowedHosts = [];

    /** @var list<int> Destination ports allowed for outbound connections. */
    public array $allowedPorts = [80, 443];

    public string $username = '';

    public string $password = '';

    /**
     * @inheritdoc
     */
    public $onMessage = null;

    /**
     * @inheritdoc
     */
    public function __construct(?string $socketName = null, array $socketContext = [])
    {
        parent::__construct($socketName, $socketContext);
        $this->onMessage = function ($connection, $buffer) {
            $requestLine = strtok($buffer, "\r\n");
            $parts = $requestLine === false ? [] : explode(' ', trim($requestLine), 3);
            if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || !in_array($parts[0], ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'CONNECT'], true)) {
                $connection->close("HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
                return;
            }

            if (!$this->isAuthorized($buffer)) {
                $connection->close("HTTP/1.1 407 Proxy Authentication Required\r\nProxy-Authenticate: Basic realm=\"Localzet Proxy\"\r\nConnection: close\r\n\r\n");
                return;
            }

            [$method, $target] = $parts;
            if ($method === 'CONNECT') {
                $targetData = parse_url('tcp://' . $target);
            } else {
                $targetData = parse_url($target);
                if (($targetData['scheme'] ?? null) !== 'http') {
                    $connection->close("HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
                    return;
                }
            }

            if (!is_array($targetData)) {
                $connection->close("HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
                return;
            }

            $host = strtolower(rtrim($targetData['host'] ?? '', '.'));
            $port = $targetData['port'] ?? ($method === 'CONNECT' ? null : 80);
            if ($host === '' || !is_int($port) || $port < 1 || $port > 65535 || !$this->isDestinationAllowed($host, $port)) {
                $connection->close("HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n");
                return;
            }

            $remoteConnection = new Server\Connection\AsyncTcpConnection("tcp://$host:$port");
            $remoteConnection->onError = static function ($remoteConnection, $code, $message) use ($connection) {
                $connection->close("HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\n\r\n");
            };

            $forwardedBuffer = $method === 'CONNECT' ? '' : $this->stripProxyHeaders($buffer);
            $remoteConnection->onConnect = static function ($remoteConnection) use ($connection, $method, $forwardedBuffer) {
                if ($method === 'CONNECT') {
                    $connection->send("HTTP/1.1 200 Connection Established\r\n\r\n");
                } else {
                    $remoteConnection->send($forwardedBuffer);
                }

                $remoteConnection->pipe($connection);
                $connection->pipe($remoteConnection);
            };

            $remoteConnection->connect();
        };
    }

    public function isDestinationAllowed(string $host, int $port): bool
    {
        $host = self::normalizeHost($host);
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $allowedHosts = array_map(
            self::normalizeHost(...),
            $this->allowedHosts
        );

        return in_array($host, $allowedHosts, true)
            && in_array($port, $this->allowedPorts, true);
    }

    private function isAuthorized(string $request): bool
    {
        if ($this->username === '' || $this->password === '') {
            return false;
        }

        foreach (preg_split('/\r?\n/', $request) ?: [] as $line) {
            if (stripos($line, 'Proxy-Authorization:') !== 0) {
                continue;
            }

            $value = trim(substr($line, strlen('Proxy-Authorization:')));
            if (preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)$/i', $value, $match) !== 1) {
                return false;
            }

            $credentials = base64_decode($match[1], true);
            return $credentials !== false
                && hash_equals($this->username . ':' . $this->password, $credentials);
        }

        return false;
    }

    private function stripProxyHeaders(string $request): string
    {
        $separator = strpos($request, "\r\n\r\n");
        if ($separator === false) {
            return $request;
        }

        $headers = explode("\r\n", substr($request, 0, $separator));
        $headers = array_values(array_filter(
            $headers,
            static fn (string $header): bool => stripos($header, 'Proxy-Authorization:') !== 0
                && stripos($header, 'Proxy-Connection:') !== 0,
        ));

        return implode("\r\n", $headers) . "\r\n\r\n" . substr($request, $separator + 4);
    }

    private static function normalizeHost(string $host): string
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return strtolower(rtrim($host, '.'));
    }
}
