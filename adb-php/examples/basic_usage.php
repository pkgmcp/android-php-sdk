<?php
/**
 * adb-php — Basic Usage Example
 *
 * Requires: ADB server running locally (adb start-server)
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb = AdbClient::create(); // 127.0.0.1:5037

echo "ADB server version: " . $adb->version() . "\n";

$devices = $adb->listDevices();
echo count($devices) . " device(s) connected:\n";
foreach ($devices as $d) {
    echo "  {$d->id}\t{$d->type}\n";
}

if (empty($devices)) {
    echo "No devices connected.\n";
    exit(0);
}

$device = $adb->getDevice($devices[0]->id);

echo "\n--- Device Info ---\n";
echo "Serial : " . $device->getSerialNo()   . "\n";
echo "State  : " . $device->getState()      . "\n";
echo "Path   : " . $device->getDevicePath() . "\n";

echo "\n--- Shell Command ---\n";
echo $device->shell('uname -a') . "\n";

echo "\n--- Properties ---\n";
$props = $device->getProperties();
$keys  = ['ro.product.model', 'ro.build.version.release', 'ro.product.cpu.abi'];
foreach ($keys as $k) {
    echo "  {$k} = " . ($props[$k] ?? 'N/A') . "\n";
}
