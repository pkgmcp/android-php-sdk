# adb-php — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] — 2026-04-26

### Added

#### AdbClient (Server Client)
- `create(host, port, timeoutMs)` — factory with PHP 8.3 typed defaults
- `version()` — ADB server version query
- `kill()` — terminate ADB server
- `connect(host, port)` / `disconnect(host, port)` — wireless ADB
- `listDevices()` → `list<Device>` — device listing
- `listDevicesWithPaths()` → `list<DeviceWithPath>` — with USB paths
- `trackDevices()` → PHP Generator yielding `add`/`remove`/`change` events
- `getDevice(serial)` → `DeviceClient` — per-device accessor
- `AdbClient::parsePublicKey(key)` → `{key, comment, fingerprint}` — RSA key parser

#### DeviceClient (50+ Methods)

**Device Info**
- `getSerialNo()`, `getState()`, `getDevicePath()`
- `getProperties()` → `array<string,string>` — all system properties
- `getFeatures()` → `array<string,string>` — hardware features
- `getPackages()` → `list<string>` — installed package names
- `getDHCPIpAddress(iface)` → `?string` — WiFi IP

**Shell**
- `shell(command)` → `string` — synchronous command execution

**App Management**
- `install(localApk)` — push + pm install + cleanup
- `installRemote(remotePath)` — pm install on-device APK
- `uninstall(package)` — pm uninstall
- `isInstalled(package)` → `bool` — presence check
- `clear(package)` — pm clear (wipe app data)

**Activity / Service**
- `startActivity(StartActivityOptions)` — `am start`
- `startService(StartActivityOptions)` — `am startservice`

**File Transfer**
- `push(local, remote, mode, ?cb)` → `PushTransfer`
- `pull(remote, ?cb)` → `{transfer, data}`

**Filesystem (SYNC)**
- `stat(path)` → `FileEntry` — remote file stat
- `readdir(path)` → `list<FileEntry>` — directory listing
- `syncService()` → `SyncService` — low-level SYNC protocol

**Port Forwarding**
- `forward(local, remote)` / `listForwards()` → `list<Forward>`
- `reverse(remote, local)` / `listReverses()` → `list<Reverse>`

**System**
- `tcpip(port)` / `usb()` — switch transport mode
- `root()` / `remount()` — root adbd, remount /system
- `reboot(mode)` — normal/bootloader/recovery/fastboot

**Screen**
- `screencap()` → `string` — PNG bytes
- `framebuffer(format)` → `{meta, data}` — raw pixel data

**Logcat**
- `openLogcat(options)` → `LogcatReader` — binary stream reader
- `openLog(name)` → `LogcatReader` — named log buffer

**Monkey**
- `openMonkey(port)` → `MonkeyClient` — UI event injector

**Process**
- `openProcStat()` → `ProcStat` — /proc/stat parser
- `trackJdwp()` → `list<int>` — debuggable PIDs

**Direct Socket**
- `openTcp(port, host)` / `openLocal(path)` → `AdbSocket`

**Wait**
- `waitForDevice(timeout)` — block until online
- `waitBootComplete(timeout)` — block until `sys.boot_completed = 1`

**Attach**
- `attach()` / `detach()` — manage ADB server attachment

#### SyncService (9 Methods)
- `stat(path)` / `readdir(path)` — filesystem queries
- `push(contents, path)` / `pushFile(local, remote)` / `pushStream(stream, path)`
- `pull(path)` → `{transfer, data}`
- `tempFile(path)` → safe temp path on device
- `end()` — close SYNC session

#### Models (All `readonly class`)
- `Device` — `{id, type}`, `__toString()`
- `DeviceWithPath` — `{id, type, path}`
- `FileEntry` — `{name, mode, size, mtime}`, `isDirectory()`, `isFile()`, `isSymlink()`
- `Forward` — `{serial, local, remote}`
- `Reverse` — `{remote, local}`
- `FramebufferMeta` — full framebuffer header (14 fields)
- `CpuStats` — `{user,nice,system,idle,iowait,irq,softirq}`, `total()`, `active()`
- `StartActivityOptions` — all `am start` flags

#### Transfers
- `PushTransfer` — `onProgress()`, `cancel()`, `isCancelled()`, `getBytesTransferred()`
- `PullTransfer` — same interface

#### Logcat
- `LogcatEntry` — `{date, pid, tid, priority, tag, message}`, `priorityLabel()`, `__toString()`
- `LogcatReader` — `read()`, `readAll()`, `stream(callback)`, `end()`

#### Monkey
- `MonkeyClient` — 17 methods: `touch`, `touchDown`, `touchUp`, `touchMove`, `keyPress`, `keyDown`, `keyUp`, `trackball`, `sleep`, `type`, `flipOpen`, `flipClosed`, `quit`, `disconnect`, `send()`

#### ProcStat
- `ProcStat` — `parse(raw)` → `{cpu: CpuStats, cores: array<string,CpuStats>}`

#### Protocol
- `AdbSocket` — raw TCP wire protocol: `send()`, `read()`, `readStatus()`, `readLengthPrefixed()`, `readAll()`, `readLine()`, `write()`

#### Exceptions
- `AdbException` — base
- `ConnectionException` — TCP connect failure
- `ProtocolException` — unexpected wire response
- `DeviceNotFoundException` — serial not found

### Tests (92 tests across 17 files)
- `AdbClientTest` — 7 tests
- `ExceptionsTest` — 5 tests
- `DeviceTest`, `FileEntryTest`, `CpuStatsTest`, `StartActivityOptionsTest` — models
- `LogcatEntryTest` — 13 tests (8 priority data providers)
- `ProcStatTest` — 6 tests
- `PushTransferTest` / `PullTransferTest` — 9 tests
- **`AdbSocketTest`** — 9 wire protocol tests
- **`SyncServiceTest`** — 9 integration tests (stat/readdir/push/pull/tempFile)
- **`LogcatReaderTest`** — 6 binary frame parsing tests
- **`DeviceClientTest`** — 11 mock-socket tests (shell/props/packages/features)
- **`MonkeyClientTest`** — 6 tests

### PHP 8.3 Features
- `readonly class` — all 8 models, LogcatEntry, ProcStat
- `const int` / `const string` / `const array` — 30+ typed constants across all classes
- `#[Override]` — transport implementations
- Named arguments, trailing commas, match, never, list<T> PHPDoc

### Docs
- `README.md` — full feature matrix, quick start, credits
- `docs/API.md` — complete method-by-method reference

### Examples (9 scripts)
- `basic_usage.php`, `install_apk.php`, `file_transfer.php`, `logcat.php`
- `monkey.php`, `track_devices.php`, `screencap.php`, `wireless_adb.php`, `procstat.php`

[1.0.0]: https://github.com/USERNAME/adb-php/releases/tag/v1.0.0
