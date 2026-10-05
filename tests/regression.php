<?php

declare(strict_types=1);

/**
 * @package Localzet Proxy
 * @link https://github.com/localzet/Proxy
 * @author Ivan Zorin <creator@localzet.com>
 * @copyright Copyright (c) 2026 Localzet Group
 * @license https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0 or later
 */

require dirname(__DIR__) . '/vendor/autoload.php';
$proxy = new localzet\Proxy('tcp://127.0.0.1:0');
if (!$proxy instanceof localzet\Server || !is_callable($proxy->onMessage)) {
    throw new RuntimeException('Proxy failed to initialize with the supported Server.');
}
$proxy->name = 'regression';
$proxy->count = 1;
if ($proxy->name !== 'regression' || $proxy->count !== 1) {
    throw new RuntimeException('Inherited server configuration is not writable.');
}
echo "Proxy class compatibility regression passed.\n";
