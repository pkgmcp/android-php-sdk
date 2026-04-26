<?php

/**
 * fastboot-php — Mock Transport Example / Integration Smoke-Test
 *
 * Runs without any physical device.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FastbootPhp\FastbootDevice;
use FastbootPhp\Transport\MockTransport;
use FastbootPhp\Common;

Common::setDebugLevel(1);

$mock = new MockTransport();

// Queue bootloader responses for getVariable('product')
$mock->queueResponse('OKAYpixel7');

// Queue response for getVariable('current-slot')
$mock->queueResponse('OKAYa');

// Queue response for getVariable('has-slot:boot')
$mock->queueResponse('OKAYno');

// Queue response for getVariable('max-download-size')
$mock->queueResponse('OKAY20000000');

// Queue DATA + OKAY for upload, then OKAY for flash:boot
$mock->queueResponse('DATA00001000');
$mock->queueResponse('OKAY');
$mock->queueResponse('OKAY');

$device = new FastbootDevice($mock);
$device->connect();

$product = $device->getVariable('product');
echo "Product      : {$product}\n"; // pixel7

$slot = $device->getVariable('current-slot');
echo "Current slot : {$slot}\n"; // a

// Simulate flashing a tiny raw image
$fakeImg = str_repeat("\x00", 0x1000);
$device->flashBlob('boot', $fakeImg, fn(float $p) => printf("  flash: %.0f%%\r", $p * 100));
echo "\n";

echo "All good!\n";

$device->disconnect();
