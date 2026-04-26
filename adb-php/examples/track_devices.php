<?php
/**
 * adb-php — Track device connect/disconnect events.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb = AdbClient::create();
echo "Tracking devices (plug/unplug devices, Ctrl+C to stop)...\n";

foreach ($adb->trackDevices() as $event) {
    $type   = $event['type'];
    $device = $event['device'];
    $icon   = match ($type) {
        'add'    => '🔌',
        'remove' => '🔴',
        default  => '🔄',
    };
    echo "{$icon} [{$type}] {$device->id} ({$device->type})\n";
}
