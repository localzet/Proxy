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
    public function close(string $response = ''): bool { $this->response = $response; return true; }
};
$proxy->allowedHosts = ['192.0.2.1'];
$authorization = base64_encode('integration-user:integration-password');
($proxy->onMessage)($connection, "GET http://127.0.0.1/ HTTP/1.1\r\nProxy-Authorization: Basic $authorization\r\n\r\n");
if (!str_starts_with($connection->response, 'HTTP/1.1 403 Forbidden')) {
    throw new RuntimeException('Proxy must reject requests outside the configured destination allowlist.');
}
$connection->response = '';
($proxy->onMessage)($connection, "GET http://127.0.0.1/ HTTP/1.1\r\n\r\n");
if (!str_starts_with($connection->response, 'HTTP/1.1 407 Proxy Authentication Required')) {
    throw new RuntimeException('Proxy must require authentication before checking destinations.');
}
echo "Proxy class compatibility regression passed.\n";
