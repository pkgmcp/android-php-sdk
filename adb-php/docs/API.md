# adb-php — Full API Reference

> Mirrors @devicefarmer/adbkit v3.3.8

---

## AdbClient

`AdbPhp\AdbClient` — entry point.

### Factory
```php
$adb = AdbClient::create(host: '127.0.0.1', port: 5037, timeoutMs: 5000);
```

### Server Management

| Method | JS equivalent | Description |
|---|---|---|
| `version(): int` | `client.version()` | ADB server version number |
| `kill(): void` | `client.kill()` | Kill ADB server |
| `connect(string $host, int $port = 5555): string` | `client.connect()` | Connect wireless ADB |
| `disconnect(string $host, int $port = 5555): string` | `client.disconnect()` | Disconnect wireless ADB |

### Device Listing

| Method | JS equivalent | Description |
|---|---|---|
| `listDevices(): Device[]` | `client.listDevices()` | List all devices |
| `listDevicesWithPaths(): DeviceWithPath[]` | `client.listDevicesWithPaths()` | List with USB paths |
| `trackDevices(): \Generator` | `client.trackDevices()` | Yield add/remove/change events |

### Device Access

| Method | JS equivalent | Description |
|---|---|---|
| `getDevice(string $serial): DeviceClient` | (new in PHP) | Get device client by serial |

### Utility

| Method | JS equivalent | Description |
|---|---|---|
| `AdbClient::parsePublicKey(string $key): array` | `adb.util.parsePublicKey()` | Parse Android RSA public key |
| `openSocket(): AdbSocket` | (internal) | Raw socket to ADB server |

---

## DeviceClient

`AdbPhp\DeviceClient` — per-device operations. Obtain via `$adb->getDevice($serial)`.

### Device Info

| Method | JS equivalent | Description |
|---|---|---|
| `getSerialNo(): string` | `device.getSerialNo()` | Serial number |
| `getState(): string` | `device.getState()` | "device" / "offline" / "unauthorized" |
| `getDevicePath(): string` | `device.getDevicePath()` | USB transport path |
| `getProperties(): array<string,string>` | `device.getProperties()` | All system properties |
| `getFeatures(): array<string,string>` | `device.getFeatures()` | Hardware/software features |
| `getPackages(): string[]` | `device.getPackages()` | Installed package names |
| `getDHCPIpAddress(?string $iface): ?string` | `device.getDHCPIpAddress()` | IP address for interface |

### Shell

| Method | JS equivalent | Description |
|---|---|---|
| `shell(string $command): string` | `device.shell()` | Run shell command, return output |

### App Management

| Method | JS equivalent | Description |
|---|---|---|
| `install(string $localApkPath): void` | `device.install()` | Push APK then install |
| `installRemote(string $remotePath): void` | `device.installRemote()` | Install APK already on device |
| `isInstalled(string $package): bool` | `device.isInstalled()` | Check if package is installed |
| `uninstall(string $package): void` | `device.uninstall()` | Uninstall package |
| `clear(string $package): void` | `device.clear()` | Clear app data & cache |

### Activities & Services

| Method | JS equivalent | Description |
|---|---|---|
| `startActivity(StartActivityOptions $opts): void` | `device.startActivity()` | Start an Activity via `am start` |
| `startService(StartActivityOptions $opts): void` | `device.startService()` | Start a Service via `am startservice` |

### File Transfer

| Method | JS equivalent | Description |
|---|---|---|
| `push(string $local, string $remote, int $mode, ?callable $onProgress): PushTransfer` | `device.push()` | Push file to device |
| `pull(string $remote, ?callable $onProgress): array` | `device.pull()` | Pull file from device |

### Filesystem

| Method | JS equivalent | Description |
|---|---|---|
| `stat(string $path): FileEntry` | `device.stat()` | Stat a remote path |
| `readdir(string $path): FileEntry[]` | `device.readdir()` | List directory contents |

### Port Forwarding

| Method | JS equivalent | Description |
|---|---|---|
| `listForwards(): Forward[]` | `device.listForwards()` | List forward rules |
| `forward(string $local, string $remote): void` | `device.forward()` | Create forward rule |
| `listReverses(): Reverse[]` | `device.listReverses()` | List reverse rules |
| `reverse(string $remote, string $local): void` | `device.reverse()` | Create reverse rule |

### TCP / USB

| Method | JS equivalent | Description |
|---|---|---|
| `tcpip(int $port = 5555): void` | `device.tcpip()` | Switch to TCP/IP mode |
| `usb(): void` | `device.usb()` | Switch back to USB mode |
| `openTcp(int $port, string $host): AdbSocket` | `device.openTcp()` | Open TCP passthrough socket |
| `openLocal(string $path): AdbSocket` | `device.openLocal()` | Open local abstract socket |

### System

| Method | JS equivalent | Description |
|---|---|---|
| `root(): void` | `device.root()` | Restart adbd as root |
| `remount(): void` | `device.remount()` | Remount /system rw |
| `reboot(string $mode = ''): void` | `device.reboot()` | Reboot (normal / bootloader / recovery) |

### Screen / Framebuffer

| Method | JS equivalent | Description |
|---|---|---|
| `screencap(): string` | `device.screencap()` | PNG screenshot bytes |
| `framebuffer(string $format): array` | `device.framebuffer()` | Raw framebuffer + metadata |

### Logcat

| Method | JS equivalent | Description |
|---|---|---|
| `openLogcat(array $options): LogcatReader` | `device.openLogcat()` | Open logcat binary stream |
| `openLog(string $name): LogcatReader` | `device.openLog()` | Open named log buffer |

### Monkey

| Method | JS equivalent | Description |
|---|---|---|
| `openMonkey(int $port = 1080): MonkeyClient` | `device.openMonkey()` | Start & connect Monkey server |

### Process

| Method | JS equivalent | Description |
|---|---|---|
| `openProcStat(): ProcStat` | `device.openProcStat()` | Read /proc/stat CPU stats |
| `trackJdwp(): int[]` | `device.trackJdwp()` | List JDWP-exposed PIDs |

### Wait

| Method | JS equivalent | Description |
|---|---|---|
| `waitForDevice(int $timeout = 60): void` | `device.waitForDevice()` | Block until device is online |
| `waitBootComplete(int $timeout = 120): void` | `device.waitBootComplete()` | Block until boot is complete |

### Sync / Attach

| Method | JS equivalent | Description |
|---|---|---|
| `syncService(): SyncService` | `device.syncService()` | Open SYNC protocol session |
| `attach(): void` | `device.attach()` | Attach device to ADB server |
| `detach(): void` | `device.detach()` | Detach device from ADB server |

---

## SyncService

`AdbPhp\SyncService` — SYNC protocol (low-level file operations).  
Obtain via `$device->syncService()`. Always call `end()` when finished.

| Method | JS equivalent | Description |
|---|---|---|
| `stat(string $path): FileEntry` | `sync.stat()` | Stat a remote path |
| `readdir(string $path): FileEntry[]` | `sync.readdir()` | List directory |
| `push(string $contents, string $path, int $mode, ?callable $cb): PushTransfer` | `sync.push()` | Push string content |
| `pushFile(string $local, string $remote, int $mode, ?callable $cb): PushTransfer` | `sync.pushFile()` | Push local file |
| `pushStream(resource $stream, string $remote, int $mode, ?callable $cb): PushTransfer` | `sync.pushStream()` | Push PHP stream |
| `pull(string $path, ?callable $cb): array` | `sync.pull()` | Pull file → string |
| `tempFile(string $path): string` | `sync.tempFile()` | Generate temp path |
| `end(): void` | `sync.end()` | Close SYNC session |

---

## LogcatReader

`AdbPhp\Logcat\LogcatReader` — reads Android binary logcat stream.

| Method | Description |
|---|---|
| `read(): ?LogcatEntry` | Read next entry (null = end of stream) |
| `readAll(): LogcatEntry[]` | Read all entries (blocks until stream ends) |
| `stream(callable $cb): void` | Yield entries to callback; return false to stop |
| `end(): void` | Close the logcat stream |

### LogcatEntry Properties

| Property | Type | Description |
|---|---|---|
| `$date` | `int` | Unix timestamp |
| `$pid` | `int` | Process ID |
| `$tid` | `int` | Thread ID |
| `$priority` | `int` | Priority constant (2–8) |
| `$tag` | `string` | Log tag |
| `$message` | `string` | Log message |
| `priorityLabel()` | `string` | V/D/I/W/E/F/S |

---

## MonkeyClient

`AdbPhp\Monkey\MonkeyClient` — Android Monkey event injector.

| Method | Description |
|---|---|
| `connect(): void` | Connect to Monkey server |
| `send(string $command): string` | Send raw command |
| `touch(int $x, int $y): void` | Tap |
| `touchDown(int $x, int $y): void` | Touch down |
| `touchUp(int $x, int $y): void` | Touch up |
| `touchMove(int $x, int $y): void` | Touch move |
| `keyPress(int\|string $keycode): void` | Key press + release |
| `keyDown(int\|string $keycode): void` | Key down |
| `keyUp(int\|string $keycode): void` | Key up |
| `trackball(int $dx, int $dy): void` | Trackball movement |
| `sleep(int $ms): void` | Sleep N ms |
| `type(string $text): void` | Type text |
| `flipOpen(): void` | Flip open |
| `flipClosed(): void` | Flip closed |
| `quit(): void` | Quit Monkey server |
| `disconnect(): void` | Close socket |

---

## ProcStat

`AdbPhp\ProcStat\ProcStat` — /proc/stat parser.

| Property | Type | Description |
|---|---|---|
| `$cpu` | `CpuStats` | Aggregate CPU stats |
| `$cores` | `array<string,CpuStats>` | Per-core stats |

### CpuStats Properties

| Property | Type |
|---|---|
| `$user` | `int` |
| `$nice` | `int` |
| `$system` | `int` |
| `$idle` | `int` |
| `$iowait` | `int` |
| `$irq` | `int` |
| `$softirq` | `int` |
| `total(): int` | Sum of all fields |
| `active(): int` | total − idle − iowait |

---

## Models

| Class | Properties |
|---|---|
| `Device` | `$id`, `$type` |
| `DeviceWithPath` | `$id`, `$type`, `$path` |
| `FileEntry` | `$name`, `$mode`, `$size`, `$mtime`, `isDirectory()`, `isFile()`, `isSymlink()` |
| `Forward` | `$serial`, `$local`, `$remote` |
| `Reverse` | `$remote`, `$local` |
| `FramebufferMeta` | `$version`, `$bpp`, `$size`, `$width`, `$height`, channel offsets/lengths, `$format` |

---

## Exceptions

| Class | Thrown when |
|---|---|
| `AdbException` | Base class for all errors |
| `ConnectionException` | Cannot connect to ADB server |
| `ProtocolException` | Unexpected ADB wire protocol response |
| `DeviceNotFoundException` | Device serial not found |
