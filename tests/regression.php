<?php

declare(strict_types=1);

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

require dirname(__DIR__) . '/vendor/autoload.php';
$proxy = new localzet\Proxy('tcp://127.0.0.1:0');
if (!$proxy instanceof localzet\Server || !is_callable($proxy->onMessage)) {
    throw new RuntimeException('Proxy failed to initialize with the supported Server.');
}
$proxy->name = 'regression';
$proxy->count = 1;
$proxy->username = 'integration-user';
$proxy->password = 'integration-password';
if ($proxy->name !== 'regression' || $proxy->count !== 1) {
    throw new RuntimeException('Inherited server configuration is not writable.');
}
if ($proxy->maxConnections !== 256) {
    throw new RuntimeException('Proxy must have a bounded per-worker connection default.');
}
$proxy->allowedHosts = ['127.0.0.1'];
if (!$proxy->isDestinationAllowed('127.0.0.1', 80) || $proxy->isDestinationAllowed('127.0.0.1', 22)) {
    throw new RuntimeException('Proxy destination policy did not enforce configured host and port.');
}
$proxy->allowedHosts = ['::1'];
if (!$proxy->isDestinationAllowed('[::1]', 80)) {
    throw new RuntimeException('Bracketed IPv6 request hosts must match literal IPv6 allowlist entries.');
}
$proxy->allowedHosts = ['example.com'];
if ($proxy->isDestinationAllowed('example.com', 80)) {
    throw new RuntimeException('Domain targets must be rejected until DNS resolution can be pinned safely.');
}
$proxy->allowedHosts = [];
if ($proxy->isDestinationAllowed('127.0.0.1', 80)) {
    throw new RuntimeException('Proxy must deny outbound destinations until an allowlist is configured.');
}
$connection = new class {
    public string $response = '';

    public object $context;

    public object $eventLoop;

    public int $maxSendBufferSize = 1_048_576;

    public int $status = \localzet\Server\Connection\TcpConnection::STATUS_ESTABLISHED;

    public $onClose = null;

    public function __construct()
    {
        $this->context = new stdClass();
        $this->eventLoop = new class {
            public int $nextTimerId = 1;

            public array $delays = [];

            public array $cancelled = [];

            public function delay(float $seconds, callable $callback, array $args = []): int
            {
                $timerId = $this->nextTimerId++;
                $this->delays[$timerId] = $seconds;
                return $timerId;
            }

            public function offDelay(int $timerId): bool
            {
                $this->cancelled[] = $timerId;
                return true;
            }
        };
    }

    public function getStatus(): int { return $this->status; }

    public function close(string $response = ''): bool { $this->response = $response; return true; }
};
$proxy->allowedHosts = ['192.0.2.1'];
$authorization = base64_encode('integration-user:integration-password');
$partialRequest = "GET http://127.0.0.1/ HTTP/1.1\r\nProxy-Authorization: Basic $authorization\r\n";
($proxy->onMessage)($connection, $partialRequest);
if ($connection->response !== '' || ($connection->context->proxyRequestHeaderBuffer ?? null) !== $partialRequest) {
    throw new RuntimeException('Proxy must wait for the full request header before authenticating or forwarding it.');
}
$headerTimerId = $connection->context->proxyHeaderTimer ?? null;
if ($headerTimerId === null || ($connection->eventLoop->delays[$headerTimerId] ?? null) !== 5.0) {
    throw new RuntimeException('Proxy must set a finite deadline for a partial request header.');
}
($proxy->onMessage)($connection, "Host: 127.0.0.1\r\n\r\n");
if (!str_starts_with($connection->response, 'HTTP/1.1 403 Forbidden')) {
    throw new RuntimeException('A completed fragmented request must continue through destination validation.');
}
if (!in_array($headerTimerId, $connection->eventLoop->cancelled, true)) {
    throw new RuntimeException('Proxy must cancel the header deadline after receiving a complete header.');
}

$appendPendingBuffer = new ReflectionMethod(localzet\Proxy::class, 'appendPendingBuffer');
$connection->maxSendBufferSize = 8;
$connection->context->proxyPendingBuffer = '';
if ($appendPendingBuffer->invoke(null, $connection, '12345678') !== true
    || $connection->context->proxyPendingBuffer !== '12345678'
    || $appendPendingBuffer->invoke(null, $connection, '9') !== false
    || $connection->context->proxyPendingBuffer !== '12345678'
) {
    throw new RuntimeException('Proxy must accept a pending buffer at the exact maxSendBufferSize boundary only.');
}

$overflowUpstream = new class extends \localzet\Server\Connection\AsyncTcpConnection {
    public bool $closed = false;

    public function __construct() {}

    public function __destruct() {}

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->closed = true;
    }
};
$connection->context->proxyUpstream = $overflowUpstream;
$connection->context->proxyConnectPending = true;
$connection->response = '';
($proxy->onMessage)($connection, '9');
if (!str_starts_with($connection->response, 'HTTP/1.1 413 Content Too Large')
    || !$overflowUpstream->closed
    || isset($connection->context->proxyUpstream)
) {
    throw new RuntimeException('Proxy must reject queued client data beyond maxSendBufferSize.');
}

$clientCanUseUpstream = new ReflectionMethod(localzet\Proxy::class, 'clientCanUseUpstream');
$connection->context->proxyUpstream = $overflowUpstream;
$connection->status = \localzet\Server\Connection\TcpConnection::STATUS_ESTABLISHED;
if ($clientCanUseUpstream->invoke(null, $connection, $overflowUpstream) !== true) {
    throw new RuntimeException('Proxy must allow upstream flushing while the client remains established.');
}
$connection->status = \localzet\Server\Connection\TcpConnection::STATUS_CLOSED;
if ($clientCanUseUpstream->invoke(null, $connection, $overflowUpstream) !== false) {
    throw new RuntimeException('Proxy must not flush queued data after the client has closed.');
}
unset($connection->context->proxyUpstream);
$connection->status = \localzet\Server\Connection\TcpConnection::STATUS_ESTABLISHED;

$connection->response = '';
$connection->maxSendBufferSize = 1_048_576;
($proxy->onMessage)($connection, "GET http://127.0.0.1/ HTTP/1.1\r\n" . str_repeat('x', 16_385));
if (!str_starts_with($connection->response, 'HTTP/1.1 431 Request Header Fields Too Large')) {
    throw new RuntimeException('Proxy must reject request headers beyond 16 KiB.');
}

$connection->response = '';
($proxy->onMessage)($connection, "GET http://127.0.0.1/ HTTP/1.1\r\nProxy-Authorization: Basic $authorization\r\n\r\n");
if (!str_starts_with($connection->response, 'HTTP/1.1 403 Forbidden')) {
    throw new RuntimeException('Proxy must reject requests outside the configured destination allowlist.');
}
$connection->response = '';
($proxy->onMessage)($connection, "GET http://127.0.0.1/ HTTP/1.1\r\n\r\n");
if (!str_starts_with($connection->response, 'HTTP/1.1 407 Proxy Authentication Required')) {
    throw new RuntimeException('Proxy must require authentication before checking destinations.');
}

$connection->response = '';
($proxy->onMessage)($connection, "GET http://127.0.0.1/ HTTP/1.1\r\n");
$closeTimerId = $connection->context->proxyHeaderTimer ?? null;
$closeUpstream = new class extends \localzet\Server\Connection\AsyncTcpConnection {
    public bool $closed = false;

    public function __construct() {}

    public function __destruct() {}

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->closed = true;
    }
};
$connection->context->proxyUpstream = $closeUpstream;
$connection->status = \localzet\Server\Connection\TcpConnection::STATUS_CLOSED;
($connection->onClose)($connection);
if ($closeTimerId === null
    || !in_array($closeTimerId, $connection->eventLoop->cancelled, true)
    || !$closeUpstream->closed
    || isset($connection->context->proxyRequestHeaderBuffer)
) {
    throw new RuntimeException('Client close must cancel header timeout and release the upstream connection.');
}

echo "Proxy class compatibility regression passed.\n";
