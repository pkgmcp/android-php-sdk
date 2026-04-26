# fastboot-php — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] — 2026-04-26

### Added

#### Core
- `FastbootDevice` — full USB fastboot client with 18 public methods
- `Sparse` — Android sparse image format parser, converter, and splitter
- `Common` — typed-constant debug logger (`LEVEL_SILENT`, `LEVEL_DEBUG`, `LEVEL_VERBOSE`)
- `CommandResponse` — readonly value object for bootloader responses
- `FastbootError` — bootloader FAIL exception with `status` + `bootloaderMessage`
- `UsbError` — USB transport layer exception

#### Transports
- `LibUsbTransport` — Linux `/dev/bus/usb/*` direct character device I/O with `#[Override]`
- `TcpTransport` — TCP socket transport for ADB-forwarded fastboot with `#[Override]`
- `MockTransport` — in-memory mock for offline unit testing with `#[Override]`
- `UsbTransportInterface` — pluggable transport contract

#### Device Operations
- `connect()`, `disconnect()`, `isConnected()` — USB lifecycle
- `runCommand(string)` — raw fastboot command execution
- `getVariable(string)`, `getMaxDownloadSize()` — bootloader variable queries
- `upload(partition, data, ?cb)` — raw payload upload with progress callback
- `flashBlob(partition, data, ?cb)` — partition flashing with:
  - A/B slot auto-resolution
  - Raw → sparse image conversion
  - Oversized image splitting
  - Progress callback (0.0–1.0)
- `flashFactoryZip(zipData, wipe, ?cb)` — full AOSP factory ZIP flashing
  - Correct flash order (bootloader → radio → partitions)
  - Inter-partition reboot for firmware stages
  - Optional userdata/cache wipe
- `erase(partition)`, `lock()`, `unlock()`
- `reboot()`, `rebootBootloader()`, `rebootRecovery()`, `rebootFastbootd()`

### Tests (78 tests across 9 files)
- `FastbootDeviceTest` — 20 tests: connect, runCommand, getVariable, upload, reboot, typed constants
- `SparseTest` — 12 tests: isSparse, toSparse, split, data providers, typed constants
- `CommonTest` — 6 tests: levels, clamping, log output
- `CommandResponseTest` — 3 tests: readonly, properties
- `FastbootErrorTest` — 4 tests: constructor, hierarchy, previous
- `UsbErrorTest` — 3 tests: hierarchy, code, previous
- `MockTransportTest` — 9 tests: queue, dequeue, trim, cancel, clearState
- **`FlashBlobTest`** — 7 integration tests: full chain (raw→sparse→split→upload→flash→progress)
- **`TransportTest`** — 6 integration tests: error guards, injected streams

### PHP 8.3 Features Used
- `readonly class` — `CommandResponse`
- `const int` — `BULK_TRANSFER_SIZE`, `DEFAULT_DOWNLOAD_SIZE`, `MAX_DOWNLOAD_SIZE`, sparse constants, `LEVEL_*`
- `#[Override]` — all transport implementations
- Named arguments, trailing commas, `match` expressions, `never` return types

### Docs
- `OVERVIEW.md` — feature list, motivation, credit
- `INSTALL.md` — composer install, udev rules, TCP transport setup
- `API.md` — full method-by-method reference
- `TRANSPORT.md` — transport guide, custom transport example
- `ERROR_HANDLING.md` — exception patterns, recommended try/catch blocks
- `PORTING_NOTES.md` — JS→PHP decisions (async→sync, WebUSB→interface, Blob→string)

### Examples
- `basic_usage.php` — connect → getvar → flash → reboot
- `factory_flash.php` — full AOSP factory ZIP flashing
- `tcp_usage.php` — TCP transport via `adb forward`
- `mock_test.php` — offline smoke test with MockTransport

[1.0.0]: https://github.com/USERNAME/fastboot-php/releases/tag/v1.0.0
