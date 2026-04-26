<?php

/**
 * fastboot-php — TCP Transport Example
 *
 * Connects via a TCP socket forwarded by ADB:
 *   adb forward tcp:5556 localabstract:fastboot
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FastbootPhp\Common;
use FastbootPhp\FastbootDevice;
use FastbootPhp\Transport\TcpTransport;

Common::setDebugLevel(1);

$transport = new TcpTransport('127.0.0.1', 5556);
$device    = new FastbootDevice($transport);

$device->connect();
echo "Connected via TCP!\n";

$product = $device->getVariable('product');
echo "Product: {$product}\n";

$device->disconnect();
