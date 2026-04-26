# Porting Notes — fastboot.js → fastboot-php

This document records design decisions made while porting fastboot.js to PHP.

---

## Key Differences

### 1. Synchronous vs Asynchronous

fastboot.js is fully `async/await`-based (WebUSB is Promise-based).  
PHP is synchronous; all I/O blocks the calling thread.

**Decision:** All methods are synchronous.  Long operations (upload, flash) accept a `$onProgress` callback that is called during the blocking loop, giving the caller a hook for progress reporting.

---

### 2. WebUSB → UsbTransportInterface

JavaScript fastboot.js calls `navigator.usb.*` directly.  
PHP has no native WebUSB.

**Decision:** Introduce `UsbTransportInterface` as an abstraction layer.  Two concrete implementations ship:
- `LibUsbTransport` — Linux `/dev/bus/usb` character device
- `TcpTransport` — TCP socket (ADB forward)

A third `MockTransport` exists solely for testing.

---

### 3. `ArrayBuffer` / `Blob` → PHP `string`

JavaScript uses `ArrayBuffer` and `Blob` for binary data.  
PHP strings are binary-safe byte strings.

**Decision:** All binary payloads are native PHP `string` values.  No wrappers needed.

---

### 4. USB Disconnect / Reconnect Events

fastboot.js registers `navigator.usb` connect/disconnect event listeners and supports `waitForConnect()` / `waitForDisconnect()` for reboot-into-fastboot flows.

PHP cannot receive async USB events.

**Decision:** After a reboot step (e.g., during factory flashing), the code `sleep()`s and then calls `connect()` again.  If you need event-driven reconnect, implement it at the transport level.

---

### 5. Browser Sandbox → PHP Filesystem

fastboot.js receives files as `Blob` / `File` objects from browser file pickers.  
PHP receives raw file paths or strings.

**Decision:** All flash/upload methods accept raw PHP `string` (binary content).  Use `file_get_contents()` to read files from disk.

---

### 6. ZipArchive for Factory ZIP

fastboot.js uses `@zip.js/zip.js` (a browser-compatible ZIP library) to unpack factory ZIPs.

**Decision:** PHP's built-in `ZipArchive` extension (bundled with PHP) is used.  It writes to a temp file internally since `ZipArchive::open()` requires a file path.

---

### 7. Sparse Image Splitting

The sparse image splitter in fastboot.js iterates chunks and re-assembles sub-images.  The same algorithm is implemented verbatim in `Sparse::split()` using PHP's `pack()`/`unpack()` for binary struct handling.

---

### 8. Progress Callbacks

JavaScript uses `onProgress = () => {}` default arrow functions.  
PHP mirrors this with `?callable $onProgress = null`, coalesced to a no-op inside the method.

---

## Class Mapping

| fastboot.js | fastboot-php |
|---|---|
| `UsbError` (class) | `FastbootPhp\UsbError` |
| `FastbootError` (class) | `FastbootPhp\FastbootError` |
| `FastbootDevice` (class) | `FastbootPhp\FastbootDevice` |
| `common.js` (module) | `FastbootPhp\Common` |
| `sparse.js` (module) | `FastbootPhp\Sparse` |
| `factory.js` → `flashZip()` | `FastbootDevice::flashFactoryZip()` |
| `setDebugLevel()` (global) | `Common::setDebugLevel()` (static) |
| `USER_ACTION_MAP` (global const) | Inline strings in `flashFactoryZip()` |
| `navigator.usb.*` (WebUSB) | `UsbTransportInterface` |
| `@zip.js/zip.js` (npm) | `ext-zip` (`ZipArchive`) |
