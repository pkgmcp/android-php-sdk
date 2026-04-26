<?php

/**
 * fastboot-php — Basic Usage Example
 *
 * Demonstrates connect → getvar → erase → flash → reboot.
 *
 * Prerequisites (Linux):
 *   1. udev rule allowing access to the USB device (see docs/INSTALL.md)
 *   2. Device in fastboot mode (`adb reboot bootloader`)
 *   3. Find your device path: `lsusb` → e.g. Bus 001 Device 005
 *      → `/dev/bus/usb/001/005`
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FastbootPhp\Common;
use FastbootPhp\FastbootDevice;
use FastbootPhp\Transport\LibUsbTransport;
use FastbootPhp\UsbError;
use FastbootPhp\FastbootError;

// Enable debug output (0 = silent, 1 = debug, 2 = verbose)
Common::setDebugLevel(1);

$transport = new LibUsbTransport('/dev/bus/usb/001/005');
$device    = new FastbootDevice($transport);

try {
    $device->connect();
    echo "Connected!\n";

    // Read device info
    $product   = $device->getVariable('product');
    $slot      = $device->getVariable('current-slot');
    $maxDl     = $device->getMaxDownloadSize();

    echo "Product       : {$product}\n";
    echo "Current slot  : {$slot}\n";
    echo "Max DL size   : " . number_format($maxDl) . " bytes\n";

    // Flash a boot image
    $bootImg = file_get_contents('/path/to/boot.img');
    $device->flashBlob('boot', $bootImg, function (float $progress): void {
        printf("  Flashing boot ... %.0f%%\r", $progress * 100);
    });
    echo "\n  boot.img flashed!\n";

    // Reboot
    $device->reboot();
    echo "Rebooting …\n";

} catch (UsbError $e) {
    echo "USB Error: " . $e->getMessage() . "\n";
} catch (FastbootError $e) {
    echo "Fastboot Error [{$e->status}]: " . $e->bootloaderMessage . "\n";
} finally {
    $device->disconnect();
}
