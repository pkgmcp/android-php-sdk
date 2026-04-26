<?php

/**
 * fastboot-php — Factory Image Flashing Example
 *
 * Flashes a full AOSP factory image ZIP (e.g. Pixel factory images from
 * https://developers.google.com/android/images).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FastbootPhp\Common;
use FastbootPhp\FastbootDevice;
use FastbootPhp\Transport\LibUsbTransport;

Common::setDebugLevel(1);

$zipPath   = $argv[1] ?? '/path/to/factory.zip';
$wipe      = isset($argv[2]) && $argv[2] === '--wipe';

$transport = new LibUsbTransport('/dev/bus/usb/001/005');
$device    = new FastbootDevice($transport);

$device->connect();
echo "Connected. Starting factory flash of: {$zipPath}\n";
if ($wipe) {
    echo "  (userdata WILL be wiped)\n";
}

$zipData = file_get_contents($zipPath);

$device->flashFactoryZip(
    $zipData,
    $wipe,
    function (string $action, string $item, float $progress): void {
        printf("[%s] %-20s %.0f%%\n", strtoupper($action), $item, $progress * 100);
    }
);

echo "Factory flash complete!\n";
$device->disconnect();
