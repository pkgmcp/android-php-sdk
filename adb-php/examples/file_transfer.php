<?php
/**
 * adb-php — Push / Pull file transfer.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb    = AdbClient::create();
$device = $adb->getDevice('emulator-5554');

// ---- Push a file ----
$transfer = $device->push('/tmp/hello.txt', '/sdcard/hello.txt', 0o644, function (int $bytes): void {
    printf("Pushed: %d bytes\r", $bytes);
});
echo "\nPush complete: {$transfer->getBytesTransferred()} bytes\n";

// ---- Pull a file ----
['data' => $data, 'transfer' => $pull] = $device->pull('/sdcard/hello.txt', function (int $bytes): void {
    printf("Pulled: %d bytes\r", $bytes);
});
echo "\nPull complete: {$pull->getBytesTransferred()} bytes\n";
echo "Contents: {$data}\n";

// ---- Stat ----
$entry = $device->stat('/sdcard/hello.txt');
echo "Size: {$entry->size}, mtime: {$entry->mtime}, dir: " . ($entry->isDirectory() ? 'yes' : 'no') . "\n";

// ---- Readdir ----
$entries = $device->readdir('/sdcard');
echo "\n/sdcard contents:\n";
foreach ($entries as $e) {
    $type = $e->isDirectory() ? 'd' : '-';
    printf("  %s %-40s %d bytes\n", $type, $e->name, $e->size);
}
