<?php
/**
 * adb-php — Screenshot / Screencap.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb    = AdbClient::create();
$device = $adb->getDevice('emulator-5554');

$png = $device->screencap();
$out = $argv[1] ?? '/tmp/screenshot.png';
file_put_contents($out, $png);
echo "Screenshot saved to: {$out} (" . strlen($png) . " bytes)\n";
