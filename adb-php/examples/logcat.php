<?php
/**
 * adb-php — Read logcat stream.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb    = AdbClient::create();
$device = $adb->getDevice('emulator-5554');

echo "Opening logcat stream (Ctrl+C to stop)...\n";

$reader = $device->openLogcat(['clear' => false]);

$reader->stream(function (\AdbPhp\Logcat\LogcatEntry $entry): bool {
    printf("[%s] %s/%s(%d): %s\n",
        date('H:i:s', $entry->date),
        $entry->priorityLabel(),
        $entry->tag,
        $entry->pid,
        $entry->message
    );
    return true; // keep streaming; return false to stop
});

$reader->end();
