# Android PHP SDK

> Complete pure-PHP 8.3 implementation of Android Fastboot + ADB protocols

[![PHP ≥ 8.3](https://img.shields.io/badge/PHP-%3E%3D8.3-8892bf)](https://php.net)
[![Tests](https://img.shields.io/badge/Tests-170%20tests-green)](tests/)
[![License](https://img.shields.io/badge/License-MIT%2FApache-blue)](LICENSE)

---

## Packages

| Package | Port Of | Tests | Coverage |
|---|---|---|---|
| [`fastboot-php/`](fastboot-php/) | [fastboot.js](https://github.com/kdrag0n/fastboot.js) | 78 | ~89–95% |
| [`adb-php/`](adb-php/) | [@devicefarmer/adbkit](https://github.com/devicefarmer/adbkit) v3.3.8 | 92 | ~84–97% |

## Quick Start

```bash
composer require fastboot-php/fastboot-php
composer require adb-php/adb-php
```

```php
// Flash firmware
use FastbootPhp\FastbootDevice;
$device = new FastbootDevice(new \FastbootPhp\Transport\LibUsbTransport('/dev/bus/usb/001/005'));
$device->connect();
$device->flashBlob('boot', file_get_contents('boot.img'));
$device->reboot();

// ADB shell, install, screenshot
use AdbPhp\AdbClient;
$adb = AdbClient::create();
$device = $adb->getDevice('emulator-5554');
$device->install('/path/to/app.apk');
file_put_contents('/tmp/screen.png', $device->screencap());
```

## Features

- **Zero native extensions** — pure PHP 8.3 over USB/TCP
- **Zero shell exec** — direct wire protocol implementation
- **170 tests** — all pass offline, no device required
- **Full coverage** — fastboot, adb, sparse images, logcat, monkey, sync
- **Modern PHP** — `readonly class`, typed constants, `#[Override]`, named args

## Run Tests

```bash
cd fastboot-php && composer install && ./vendor/bin/phpunit
cd adb-php && composer install && ./vendor/bin/phpunit
```

## Docs

- [fastboot-php API](fastboot-php/docs/API.md)
- [adb-php API](adb-php/docs/API.md)
- [HTML Docs Site](docs-site/index.html) — open in browser

## License

- `fastboot-php/` — MIT
- `adb-php/` — Apache 2.0
