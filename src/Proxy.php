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
    private const MAX_REQUEST_HEADER_SIZE = 16_384;
    private const REQUEST_HEADER_TIMEOUT = 5.0;
    private const DEFAULT_MAX_CONNECTIONS = 256;

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
        $this->maxConnections = self::DEFAULT_MAX_CONNECTIONS;
        $this->onMessage = function ($connection, $buffer) {
            self::installClientCleanup($connection);

            if (($connection->context->proxyConnectPending ?? false) === true) {
                if (!self::appendPendingBuffer($connection, $buffer)) {
                    self::closeUpstream($connection);
                    unset($connection->context->proxyConnectPending, $connection->context->proxyPendingBuffer);
                    $connection->close("HTTP/1.1 413 Content Too Large\r\nConnection: close\r\n\r\n");
                    return;
                }

                return;
            }

            $buffer = (string)($connection->context->proxyRequestHeaderBuffer ?? '') . $buffer;
            $headerEnd = strpos($buffer, "\r\n\r\n");
            if ($headerEnd === false) {
                if (strlen($buffer) > self::MAX_REQUEST_HEADER_SIZE) {
                    unset($connection->context->proxyRequestHeaderBuffer);
                    self::cancelRequestHeaderTimer($connection);
                    $connection->close("HTTP/1.1 431 Request Header Fields Too Large\r\nConnection: close\r\n\r\n");
                    return;
                }

                $connection->context->proxyRequestHeaderBuffer = $buffer;
                self::armRequestHeaderTimer($connection);
                return;
            }

            if ($headerEnd + 4 > self::MAX_REQUEST_HEADER_SIZE) {
                unset($connection->context->proxyRequestHeaderBuffer);
                self::cancelRequestHeaderTimer($connection);
                $connection->close("HTTP/1.1 431 Request Header Fields Too Large\r\nConnection: close\r\n\r\n");
                return;
            }

            unset($connection->context->proxyRequestHeaderBuffer);
            self::cancelRequestHeaderTimer($connection);

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

            $requestPayload = substr($buffer, $headerEnd + 4);
            $initialBuffer = $method === 'CONNECT' ? $requestPayload : $this->stripProxyHeaders($buffer);
            if (!self::appendPendingBuffer($connection, $initialBuffer)) {
                $connection->close("HTTP/1.1 413 Content Too Large\r\nConnection: close\r\n\r\n");
                return;
            }

            $remoteConnection = new Server\Connection\AsyncTcpConnection("tcp://$host:$port");
            $connection->context->proxyUpstream = $remoteConnection;
            $connection->context->proxyConnectPending = true;

            $remoteConnection->onError = static function ($remoteConnection) use ($connection): void {
                if (($connection->context->proxyUpstream ?? null) !== $remoteConnection) {
                    return;
                }

                unset(
                    $connection->context->proxyUpstream,
                    $connection->context->proxyConnectPending,
                    $connection->context->proxyPendingBuffer,
                );
                $remoteConnection->close();
                if ($connection->getStatus() === Server\Connection\TcpConnection::STATUS_ESTABLISHED) {
                    $connection->close("HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\n\r\n");
                }
            };

            $remoteConnection->onClose = static function ($remoteConnection) use ($connection): void {
                if (($connection->context->proxyUpstream ?? null) !== $remoteConnection) {
                    return;
                }

                unset($connection->context->proxyUpstream, $connection->context->proxyConnectPending, $connection->context->proxyPendingBuffer);
                if ($connection->getStatus() === Server\Connection\TcpConnection::STATUS_ESTABLISHED) {
                    $connection->close();
                }
            };

            $remoteConnection->onConnect = static function ($remoteConnection) use ($connection, $method): void {
                if (!self::clientCanUseUpstream($connection, $remoteConnection)) {
                    $remoteConnection->close();
                    return;
                }

                $pendingBuffer = (string)($connection->context->proxyPendingBuffer ?? '');
                unset($connection->context->proxyConnectPending, $connection->context->proxyPendingBuffer);

                $connection->onMessage = static fn ($source, $data) => $remoteConnection->send($data, true);
                $remoteConnection->onMessage = static fn ($source, $data) => $connection->send($data, true);
                $remoteConnection->onBufferFull = static fn () => $connection->pauseRecv();
                $remoteConnection->onBufferDrain = static fn () => $connection->resumeRecv();
                $connection->onBufferFull = static fn () => $remoteConnection->pauseRecv();
                $connection->onBufferDrain = static fn () => $remoteConnection->resumeRecv();

                if ($method === 'CONNECT') {
                    $connection->send("HTTP/1.1 200 Connection Established\r\n\r\n");
                }

                if ($pendingBuffer !== '') {
                    $remoteConnection->send($pendingBuffer, true);
                }
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

    private static function installClientCleanup(object $connection): void
    {
        if (($connection->context->proxyCleanupInstalled ?? false) === true) {
            return;
        }

        $connection->context->proxyCleanupInstalled = true;
        $userOnClose = $connection->onClose ?? null;
        $connection->onClose = static function ($connection) use ($userOnClose): void {
            self::cancelRequestHeaderTimer($connection);
            self::closeUpstream($connection);
            unset(
                $connection->context->proxyConnectPending,
                $connection->context->proxyPendingBuffer,
                $connection->context->proxyRequestHeaderBuffer,
            );

            if (is_callable($userOnClose)) {
                $userOnClose($connection);
            }
        };
    }

    private static function armRequestHeaderTimer(object $connection): void
    {
        if (isset($connection->context->proxyHeaderTimer)) {
            return;
        }

        $eventLoop = $connection->eventLoop ?? null;
        if ($eventLoop === null) {
            $connection->close("HTTP/1.1 500 Internal Server Error\r\nConnection: close\r\n\r\n");
            return;
        }

        $connection->context->proxyHeaderTimer = $eventLoop->delay(self::REQUEST_HEADER_TIMEOUT, static function () use ($connection): void {
            unset($connection->context->proxyHeaderTimer, $connection->context->proxyRequestHeaderBuffer);
            if ($connection->getStatus() === Server\Connection\TcpConnection::STATUS_ESTABLISHED) {
                $connection->close("HTTP/1.1 408 Request Timeout\r\nConnection: close\r\n\r\n");
            }
        });
    }

    private static function cancelRequestHeaderTimer(object $connection): void
    {
        $timerId = $connection->context->proxyHeaderTimer ?? 0;
        unset($connection->context->proxyHeaderTimer);

        if ($timerId > 0 && ($connection->eventLoop ?? null) !== null) {
            $connection->eventLoop->offDelay((int)$timerId);
        }
    }

    private static function appendPendingBuffer(object $connection, string $buffer): bool
    {
        $pendingBuffer = (string)($connection->context->proxyPendingBuffer ?? '') . $buffer;
        if (strlen($pendingBuffer) > $connection->maxSendBufferSize) {
            return false;
        }

        $connection->context->proxyPendingBuffer = $pendingBuffer;
        return true;
    }

    private static function closeUpstream(object $connection): void
    {
        $upstream = $connection->context->proxyUpstream ?? null;
        unset($connection->context->proxyUpstream);

        if ($upstream instanceof Server\Connection\AsyncTcpConnection) {
            $upstream->close();
        }
    }

    private static function clientCanUseUpstream(object $connection, object $upstream): bool
    {
        return ($connection->context->proxyUpstream ?? null) === $upstream
            && $connection->getStatus() === Server\Connection\TcpConnection::STATUS_ESTABLISHED;
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
