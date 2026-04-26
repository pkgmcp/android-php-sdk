# fastboot-php

> PHP 8.3 implementation of the Android Fastboot protocol — full port of [fastboot.js](https://github.com/kdrag0n/fastboot.js)

[![PHP ≥ 8.3](https://img.shields.io/badge/PHP-%3E%3D8.3-8892bf)](https://php.net)
[![Tests](https://img.shields.io/badge/Tests-78%20across%209%20files-green)](tests/)
[![Coverage](https://img.shields.io/badge/Coverage-89--95%25-brightgreen)](coverage/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue)](LICENSE)

---

## What Is This?

**fastboot-php** lets you communicate with Android devices in fastboot/bootloader mode directly from PHP. No native extensions, no shell exec — pure PHP over USB or TCP.

A complete, idiomatic PHP 8.3+ port of [`fastboot.js`](https://github.com/kdrag0n/fastboot.js) by Danny Lin.

---

## Requirements

- PHP **8.3+**
- `ext-zip` (for `flashFactoryZip` — bundled with PHP)
- Linux udev rules for `LibUsbTransport` (optional — `TcpTransport` works without)

---

## Installation

```bash
composer require fastboot-php/fastboot-php
```

---

## Quick Start

```php
use FastbootPhp\FastbootDevice;
use FastbootPhp\Common;
use FastbootPhp\Transport\LibUsbTransport;

Common::setDebugLevel(Common::LEVEL_DEBUG);

$device = new FastbootDevice(new LibUsbTransport('/dev/bus/usb/001/005'));
$device->connect();

echo $device->getVariable('product');  // "pixel7"
echo $device->getVariable('current-slot');  // "a"

$device->flashBlob('boot', file_get_contents('boot.img'), fn($p) => printf("%.0f%%\r", $p*100));
$device->reboot();
$device->disconnect();
```

---

## Full Feature Map

| Category | Method | Status |
|---|---|---|
| **Lifecycle** | `connect()`, `disconnect()`, `isConnected()` | ✅ |
| **Commands** | `runCommand(string)` | ✅ |
| **Variables** | `getVariable(string)`, `getMaxDownloadSize()` | ✅ |
| **Upload** | `upload(partition, data, ?cb)` | ✅ |
| **Flash** | `flashBlob(partition, data, ?cb)` | ✅ |
| **Flash** | `flashFactoryZip(zip, wipe, ?cb)` | ✅ |
| **Erase** | `erase(partition)` | ✅ |
| **Lock** | `lock()`, `unlock()` | ✅ |
| **Reboot** | `reboot()`, `rebootBootloader()`, `rebootRecovery()`, `rebootFastbootd()` | ✅ |

---

## Missing Features (Known)

| Feature | Status | Reason |
|---|---|---|
| Real USB bulk-OUT with progress interrupts | 🟡 Partial | `LibUsbTransport` uses basic fread/fwrite; libusb FFI would add cancellation support |
| Sparse image DONT_CARE / FILL chunk types | 🟡 Partial | Only RAW chunks are generated; DONT_CARE detection would improve efficiency for zero-filled regions |
| Super partition flashing | 🔴 Not implemented | Requires LP metadata parsing (super.img) — not in fastboot.js either |
| Custom AVB key flashing | 🔴 Not implemented | Requires pk45 parsing — niche use case |
| `verify()` / `get_staged()` commands | 🔴 Not implemented | Bootloader-specific, not universally supported |

> 📌 All missing features are either hardware-dependent, bootloader-specific, or were never in the upstream fastboot.js.

---

## PHP 8.3 Features

| Feature | Used In |
|---|---|
| `readonly class` | `CommandResponse` |
| `const int` | 11 typed constants across 3 classes |
| `#[Override]` | All 3 transport classes (21 methods) |
| Named arguments | Throughout |
| `match` expression | `FastbootDevice::readResponse()` |
| `never` return type | Error helpers |

---

## Testing

```bash
composer install
./vendor/bin/phpunit --colors=always
```

**78 tests** across 9 files — all pass offline with no device required.

---

## File Structure

```
fastboot-php/
├── composer.json
├── README.md
├── CHANGELOG.md
├── SECURITY.md
├── LICENSE
├── phpunit.xml
├── src/
│   ├── FastbootDevice.php      # Main client (18 public methods)
│   ├── Sparse.php              # Sparse image parser/converter/splitter
│   ├── Common.php              # Debug logging (typed constants)
│   ├── CommandResponse.php     # readonly value object
│   ├── FastbootError.php       # Bootloader exception
│   ├── UsbError.php            # USB exception
│   ├── Contracts/
│   │   └── UsbTransportInterface.php
│   └── Transport/
│       ├── LibUsbTransport.php
│       ├── TcpTransport.php
│       └── MockTransport.php
├── tests/Unit/
│   ├── FastbootDeviceTest.php
│   ├── SparseTest.php
│   ├── CommonTest.php
│   ├── CommandResponseTest.php
│   ├── FastbootErrorTest.php
│   ├── UsbErrorTest.php
│   ├── Transport/
│   │   └── MockTransportTest.php
│   └── Integration/
│       ├── FlashBlobTest.php
│       └── TransportTest.php
├── examples/
│   ├── basic_usage.php
│   ├── factory_flash.php
│   ├── tcp_usage.php
│   └── mock_test.php
└── docs/
    ├── OVERVIEW.md
    ├── INSTALL.md
    ├── API.md
    ├── TRANSPORT.md
    ├── ERROR_HANDLING.md
    └── PORTING_NOTES.md
```

---

## Credits

- Original: [fastboot.js](https://github.com/kdrag0n/fastboot.js) by Danny Lin — MIT License
- Android sparse image format: [AOSP libsparse](https://android.googlesource.com/platform/system/core/+/refs/heads/main/libsparse/)

---

## License

MIT — see [LICENSE](LICENSE).
