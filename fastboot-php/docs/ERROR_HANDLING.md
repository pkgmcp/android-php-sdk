# Error Handling

## Exception Hierarchy

```
\RuntimeException
├── FastbootPhp\UsbError       — USB-layer problems
└── FastbootPhp\FastbootError  — Bootloader-level failures
```

---

## UsbError

Thrown by transport implementations for hardware-level issues:

- Device not found
- Could not open / claim interface
- Read or write timed out
- Short write (partial data sent)

```php
try {
    $device->connect();
} catch (\FastbootPhp\UsbError $e) {
    echo "USB problem: " . $e->getMessage();
}
```

---

## FastbootError

Thrown when the bootloader responds with `FAIL`, or when a high-level operation detects a protocol error.

```php
try {
    $device->erase('userdata');
} catch (\FastbootPhp\FastbootError $e) {
    echo "Bootloader said [{$e->status}]: {$e->bootloaderMessage}";
}
```

### Common bootloader error messages

| Message | Cause |
|---|---|
| `"not allowed"` | OEM unlock not enabled, or bootloader is locked |
| `"invalid partition"` | Partition name doesn't exist on this device |
| `"download failure"` | Transfer was too large or corrupted |
| `"Command not found"` | Command not supported by this bootloader version |

---

## Recommended Pattern

```php
use FastbootPhp\FastbootDevice;
use FastbootPhp\FastbootError;
use FastbootPhp\UsbError;
use FastbootPhp\Transport\LibUsbTransport;

$device = new FastbootDevice(new LibUsbTransport('/dev/bus/usb/001/005'));

try {
    $device->connect();
    $device->flashBlob('boot', file_get_contents('boot.img'));
    $device->reboot();
} catch (UsbError $e) {
    // Hardware / transport problem
    fwrite(STDERR, "USB error: " . $e->getMessage() . "\n");
    exit(1);
} catch (FastbootError $e) {
    // Bootloader rejected the operation
    fwrite(STDERR, "Bootloader error [{$e->status}]: {$e->bootloaderMessage}\n");
    exit(2);
} finally {
    $device->disconnect();
}
```
