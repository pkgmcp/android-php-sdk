# API Reference

---

## Common

`FastbootPhp\Common`

Shared logging and configuration utilities. Mirrors `common.js`.

### Methods

#### `Common::setDebugLevel(int $level): void`

Set the global log verbosity.

| Level | Meaning |
|---|---|
| `0` | Silent (default) |
| `1` | Debug — recommended for general use |
| `2` | Verbose — for deep debugging only |

#### `Common::getDebugLevel(): int`

Returns the current debug level.

#### `Common::logDebug(string $message, mixed ...$context): void`

Emits a debug message to `STDERR` when level ≥ 1.

#### `Common::logVerbose(string $message, mixed ...$context): void`

Emits a verbose message to `STDERR` when level ≥ 2.

---

## UsbError

`FastbootPhp\UsbError extends \RuntimeException`

Thrown for USB transport errors (device not found, open failed, short read/write, timeout, etc.).

---

## FastbootError

`FastbootPhp\FastbootError extends \RuntimeException`

Thrown when the bootloader returns a `FAIL` response, or when a high-level fastboot operation fails due to a bootloader response.

### Properties

| Property | Type | Description |
|---|---|---|
| `$status` | `string` | Raw bootloader status (e.g. `"FAIL"`) |
| `$bootloaderMessage` | `string` | Raw message body from the bootloader |

### Constructor

```php
new FastbootError(string $status, string $message, ?\Throwable $previous = null)
```

---

## CommandResponse

`FastbootPhp\CommandResponse`

Immutable value object returned by `FastbootDevice::runCommand()`.

### Properties

| Property | Type | Description |
|---|---|---|
| `$text` | `string` | Concatenated `INFO` / `OKAY` message text |
| `$dataSize` | `string\|null` | Hex-encoded data size from a `DATA` response, or `null` |

---

## FastbootDevice

`FastbootPhp\FastbootDevice`

The main class. Provides the full fastboot client API.

### Constructor

```php
new FastbootDevice(?UsbTransportInterface $transport = null)
```

### Connection

#### `connect(): void`

Open the USB transport and claim the fastboot interface.

- Throws `UsbError` on USB failures.

#### `disconnect(): void`

Release the USB interface and close the device handle.

#### `isConnected(): bool`

Returns `true` when the transport is open and ready.

#### `setTransport(UsbTransportInterface $transport): void`

Replace the transport (useful for testing).

---

### Low-Level Protocol

#### `runCommand(string $command): CommandResponse`

Send a raw fastboot command and return the full response.

```php
$response = $device->runCommand('getvar:product');
echo $response->text; // e.g. "pixel7"
```

#### `getVariable(string $varName): ?string`

Read a bootloader variable.  Returns `null` if the variable is empty or unknown.

```php
$slot = $device->getVariable('current-slot'); // "a" or "b"
```

#### `getMaxDownloadSize(): int`

Query the bootloader's maximum download buffer size. Returns a safe default (512 MiB) if not advertised.

---

### Upload

#### `upload(string $partition, string $data, ?callable $onProgress = null): void`

Upload a raw payload for a given partition.  Does not flash; use `flashBlob()` for that.

**Progress callback:** `function(float $progress): void` — `$progress` is 0.0–1.0.

---

### Flashing

#### `flashBlob(string $partition, string $data, ?callable $onProgress = null): void`

Flash an image to a named partition.

- Auto-detects and resolves A/B slots.
- Converts raw images to sparse automatically.
- Splits oversized sparse images into multiple passes.

```php
$device->flashBlob('boot', file_get_contents('boot.img'), function(float $p) {
    printf("%.0f%%\r", $p * 100);
});
```

#### `flashFactoryZip(string $zipData, bool $wipe = false, ?callable $onProgress = null): void`

Flash a full AOSP factory image ZIP.

**Progress callback:** `function(string $action, string $item, float $progress): void`

```php
$device->flashFactoryZip(
    file_get_contents('factory.zip'),
    wipe: true,
    onProgress: fn($action, $item, $p) => printf("[%s] %s %.0f%%\n", $action, $item, $p * 100)
);
```

#### `erase(string $partition): void`

Erase the given partition.

---

### Lock / Unlock

#### `lock(): void`

Lock the bootloader (`flashing lock`).

#### `unlock(): void`

Unlock the bootloader (`flashing unlock`).

---

### Reboot

| Method | Boots into |
|---|---|
| `reboot()` | OS |
| `rebootBootloader()` | Fastboot / bootloader |
| `rebootRecovery()` | Recovery |
| `rebootFastbootd()` | Userspace fastbootd (Android 10+) |

---

## Sparse

`FastbootPhp\Sparse`

Android sparse image utilities. Mirrors `sparse.js`.

### Constants

| Constant | Value | Description |
|---|---|---|
| `SPARSE_HEADER_MAGIC` | `0xED26FF3A` | Magic number |
| `SPARSE_HEADER_SIZE` | `28` | File header size in bytes |
| `CHUNK_HEADER_SIZE` | `12` | Chunk header size in bytes |
| `CHUNK_TYPE_RAW` | `0xCAC1` | Raw data chunk |
| `CHUNK_TYPE_FILL` | `0xCAC2` | Fill chunk |
| `CHUNK_TYPE_DONT_CARE` | `0xCAC3` | Don't-care (skip) chunk |
| `CHUNK_TYPE_CRC32` | `0xCAC4` | CRC32 chunk |

### Methods

#### `Sparse::isSparse(string $data): bool`

Returns `true` if `$data` starts with the sparse image magic number.

#### `Sparse::toSparse(string $rawData, int $blockSize = 4096): string`

Convert a raw partition image into a sparse image (single RAW chunk).

#### `Sparse::split(string $sparseData, int $maxDownloadSize): string[]`

Split an oversized sparse image into sub-images, each ≤ `$maxDownloadSize` bytes.  Returns an array of sparse image byte strings.

---

## UsbTransportInterface

`FastbootPhp\Contracts\UsbTransportInterface`

Contract for USB transport implementations.

| Method | Description |
|---|---|
| `open(): void` | Open / claim the device |
| `close(): void` | Release / close the device |
| `isConnected(): bool` | Returns true when ready |
| `transferOut(string $data): void` | Bulk-OUT (host → device) |
| `transferIn(int $maxLength): string` | Bulk-IN (device → host) |
| `reset(): void` | Reset the device (best-effort) |

---

## LibUsbTransport

`FastbootPhp\Transport\LibUsbTransport`

Opens `/dev/bus/usb/<bus>/<device>` and performs raw `fread`/`fwrite` bulk transfers.

```php
new LibUsbTransport(string $devicePath, int $timeoutMs = 5000)
```

---

## TcpTransport

`FastbootPhp\Transport\TcpTransport`

TCP socket transport for ADB-forwarded or network fastboot.

```php
new TcpTransport(string $host, int $port, int $timeoutMs = 5000)
```

---

## MockTransport

`FastbootPhp\Transport\MockTransport`

In-memory mock for unit testing. No hardware required.

```php
$mock = new MockTransport();
$mock->queueResponse('OKAYpixel7'); // queued transferIn() results
$device = new FastbootDevice($mock);
$device->connect();
$product = $device->getVariable('product'); // "pixel7"
$mock->getSentData(); // ["getvar:product"]
```

### Methods

| Method | Description |
|---|---|
| `queueResponse(string $response): void` | Enqueue a raw response packet |
| `getSentData(): string[]` | Inspect all data written via `transferOut()` |
| `reset(): void` | Clear queued responses and sent data |
