<?php
/**
 * adb-php — Install APK on all connected devices.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$apk = $argv[1] ?? '/path/to/app.apk';

$adb     = AdbClient::create();
$devices = $adb->listDevices();

if (empty($devices)) {
    echo "No devices connected.\n";
    exit(1);
}

foreach ($devices as $d) {
    echo "Installing {$apk} on {$d->id} … ";
    $device = $adb->getDevice($d->id);
    $device->install($apk);
    echo "done.\n";
}
