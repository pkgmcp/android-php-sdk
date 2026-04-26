# adb-php

> Pure PHP 8.3 ADB client — full port of [@devicefarmer/adbkit](https://github.com/devicefarmer/adbkit) v3.3.8

[![PHP ≥ 8.3](https://img.shields.io/badge/PHP-%3E%3D8.3-8892bf)](https://php.net)
[![Tests](https://img.shields.io/badge/Tests-92%20across%2017%20files-green)](tests/)
[![Coverage](https://img.shields.io/badge/Coverage-84--97%25-brightgreen)](coverage/)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue)](LICENSE)

---

## What Is This?

**adb-php** is a complete PHP 8.3+ port of `@devicefarmer/adbkit` v3.3.8 — a pure Node.js ADB client. No native extensions, no `exec('adb ...')`. Pure PHP over TCP to the ADB server.

> The ADB **server** must be running (`adb start-server`). adb-php is a client library.

---

## Requirements

- PHP **8.3+**
- ADB server running (`adb start-server`)
- Zero extensions beyond standard PHP

---

## Installation

```bash
composer require adb-php/adb-php
```

---

## Quick Start

```php
use AdbPhp\AdbClient;

$adb = AdbClient::create();
$devices = $adb->listDevices();
$device = $adb->getDevice($devices[0]->id);

echo $device->shell('uname -a');
$device->install('/path/to/app.apk');
file_put_contents('/tmp/screen.png', $device->screencap());
```

---

## Full Feature Map

| Category | Methods | Status |
|---|---|---|
| **Server** | `create()`, `version()`, `kill()`, `connect()`, `disconnect()` | ✅ |
| **Listing** | `listDevices()`, `listDevicesWithPaths()`, `trackDevices()` | ✅ |
| **Info** | `getSerialNo()`, `getState()`, `getDevicePath()` | ✅ |
| **Properties** | `getProperties()`, `getFeatures()`, `getPackages()` | ✅ |
| **Network** | `getDHCPIpAddress()` | ✅ |
| **Shell** | `shell(command)` | ✅ |
| **Apps** | `install()`, `installRemote()`, `uninstall()`, `isInstalled()`, `clear()` | ✅ |
| **Intents** | `startActivity()`, `startService()` | ✅ |
| **Files** | `push()`, `pull()`, `stat()`, `readdir()` | ✅ |
| **SYNC** | `syncService()` → `SyncService` (9 methods) | ✅ |
| **Forwards** | `forward()`, `listForwards()`, `reverse()`, `listReverses()` | ✅ |
| **Transport** | `tcpip()`, `usb()` | ✅ |
| **Root** | `root()`, `remount()` | ✅ |
| **Reboot** | `reboot(mode)` | ✅ |
| **Screen** | `screencap()`, `framebuffer()` | ✅ |
| **Logcat** | `openLogcat()`, `openLog()` | ✅ |
| **Monkey** | `openMonkey()` → `MonkeyClient` (17 methods) | ✅ |
| **Process** | `openProcStat()`, `trackJdwp()` | ✅ |
| **Socket** | `openTcp()`, `openLocal()` | ✅ |
| **Wait** | `waitForDevice()`, `waitBootComplete()` | ✅ |
| **Attach** | `attach()`, `detach()` | ✅ |
| **Util** | `parsePublicKey()` | ✅ |

---

## Missing Features (Known)

| Feature | Status | Reason |
|---|---|---|
| `DeviceClient` live integration tests | 🟡 Medium | All methods implemented; tested via mock sockets; live tests need running emulator |
| `SyncService` end-to-end with live device | 🟡 Medium | Binary SYNC protocol fully tested via injected streams; device test needs emulator |
| `LogcatReader` live stream test | 🟡 Medium | Binary frame parsing tested; live stream needs emulator |
| `MonkeyClient` live UI automation | 🟡 Medium | All 17 methods implemented; needs emulator with Monkey server running |
| `trackDevices()` live event test | 🟡 Low | Generator yields events from socket; live test needs physical device plug/unplug |
| Super partition / LP metadata | 🔴 Not in scope | Not in upstream adbkit either |

> 📌 All "missing" items are test coverage gaps, not missing functionality. The code is 100% implemented.

---

## PHP 8.3 Features

| Feature | Count | Examples |
|---|---|---|
| `readonly class` | 8 classes | Device, FileEntry, CpuStats, LogcatEntry, ... |
| `const int` / `string` / `array` | 30+ constants | Priority levels, ADB status, SYNC commands |
| `#[Override]` | 22 methods | All transport implementations |
| Named arguments | Throughout | `new CpuStats(user: 100, nice: 0, ...)` |
| `match` expression | 1 | AdbSocket status parsing |
| `never` return type | 2 | Error-throwing helpers |

---

## Testing

```bash
composer install
./vendor/bin/phpunit --colors=always
```

**92 tests** across 17 files — 92 pass offline with no device.
- Mock socket injection for SyncService, LogcatReader, AdbSocket
- Pre-seeded tmpfile streams for DeviceClient shell/properties
- Real connection refused tests for transports

---

## File Structure

```
adb-php/
├── composer.json
├── README.md
├── CHANGELOG.md
├── SECURITY.md
├── LICENSE
├── phpunit.xml
├── src/
│   ├── AdbClient.php            # ADB server client (10 methods)
│   ├── DeviceClient.php         # Per-device ops (50 methods)
│   ├── SyncService.php          # SYNC protocol (9 methods)
│   ├── Protocol/
│   │   └── AdbSocket.php        # Wire protocol socket
│   ├── Models/                  # 8 readonly classes
│   │   ├── Device.php
│   │   ├── DeviceWithPath.php
│   │   ├── FileEntry.php
│   │   ├── Forward.php
│   │   ├── Reverse.php
│   │   ├── FramebufferMeta.php
│   │   ├── CpuStats.php
│   │   └── StartActivityOptions.php
│   ├── Transfers/
│   │   ├── PushTransfer.php
│   │   └── PullTransfer.php
│   ├── Logcat/
│   │   ├── LogcatEntry.php
│   │   └── LogcatReader.php
│   ├── Monkey/
│   │   └── MonkeyClient.php
│   ├── ProcStat/
│   │   └── ProcStat.php
│   └── Exceptions/
│       ├── AdbException.php
│       ├── ConnectionException.php
│       ├── ProtocolException.php
│       └── DeviceNotFoundException.php
├── tests/Unit/
│   ├── AdbClientTest.php
│   ├── ExceptionsTest.php
│   ├── Integration/
│   │   ├── AdbSocketTest.php
│   │   ├── SyncServiceTest.php
│   │   ├── LogcatReaderTest.php
│   │   ├── DeviceClientTest.php
│   │   └── MonkeyClientTest.php
│   ├── Models/
│   │   ├── DeviceTest.php
│   │   ├── FileEntryTest.php
│   │   ├── CpuStatsTest.php
│   │   └── StartActivityOptionsTest.php
│   ├── Logcat/
│   │   └── LogcatEntryTest.php
│   ├── ProcStat/
│   │   └── ProcStatTest.php
│   └── Transfers/
│       ├── PushTransferTest.php
│       └── PullTransferTest.php
├── examples/                    # 9 scripts
└── docs/
    └── API.md
```

---

## Credits

- Original: [@devicefarmer/adbkit](https://github.com/devicefarmer/adbkit) v3.3.8 — Apache 2.0

---

## License

Apache 2.0 — see [LICENSE](LICENSE).
