# Transport Guide

## Overview

`FastbootDevice` is fully decoupled from the USB layer via the `UsbTransportInterface`.  
Choose or implement the transport that fits your environment.

```
FastbootDevice
     │
     ▼
UsbTransportInterface
     │
     ├── LibUsbTransport   ← Linux /dev/bus/usb (direct)
     ├── TcpTransport      ← ADB-forwarded TCP socket
     └── MockTransport     ← In-memory mock (tests / CI)
```

---

## LibUsbTransport

**Best for:** Linux servers / workstations with direct USB access.

```php
use FastbootPhp\Transport\LibUsbTransport;

$t = new LibUsbTransport('/dev/bus/usb/001/005');
```

Requires udev rules — see [INSTALL.md](INSTALL.md).

---

## TcpTransport

**Best for:** Any OS, when you already have ADB running.

```bash
# Forward device fastboot socket
adb forward tcp:5556 localabstract:fastboot
```

```php
use FastbootPhp\Transport\TcpTransport;

$t = new TcpTransport('127.0.0.1', 5556);
```

---

## MockTransport

**Best for:** Unit tests, CI pipelines, offline development.

```php
use FastbootPhp\Transport\MockTransport;

$mock = new MockTransport();
$mock->queueResponse('OKAYpixel7'); // getVariable('product')

$device = new FastbootDevice($mock);
$device->connect();
```

---

## Writing a Custom Transport

Implement `UsbTransportInterface` to add support for:
- libusb via PHP FFI
- ADB over Unix sockets
- Serial/UART connections
- CI test harnesses

```php
use FastbootPhp\Contracts\UsbTransportInterface;
use FastbootPhp\UsbError;

class MyCustomTransport implements UsbTransportInterface
{
    public function open(): void   { /* ... */ }
    public function close(): void  { /* ... */ }
    public function isConnected(): bool { return /* ... */; }

    public function transferOut(string $data): void
    {
        // Send $data to device
    }

    public function transferIn(int $maxLength): string
    {
        // Read up to $maxLength bytes and return
        return ''; // replace with real read
    }

    public function reset(): void  { /* optional */ }
}
```

Pass it to `FastbootDevice`:

```php
$device = new FastbootDevice(new MyCustomTransport());
$device->connect();
```
