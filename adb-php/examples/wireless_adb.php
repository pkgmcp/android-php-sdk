<?php
/**
 * adb-php — Connect/disconnect wireless ADB.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb = AdbClient::create();

// Switch device to TCP/IP mode first (requires USB connection)
$device = $adb->getDevice($argv[1] ?? 'emulator-5554');
$device->tcpip(5555);
sleep(2);

$host = $argv[2] ?? '192.168.1.100';

// Connect wirelessly
echo $adb->connect($host, 5555) . "\n";

// Work over wifi...
$device = $adb->getDevice("{$host}:5555");
echo $device->shell('ip addr show wlan0') . "\n";

// Disconnect
echo $adb->disconnect($host, 5555) . "\n";
