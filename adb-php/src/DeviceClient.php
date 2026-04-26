<?php

declare(strict_types=1);

namespace AdbPhp;

use AdbPhp\Exceptions\AdbException;
use AdbPhp\Exceptions\ProtocolException;
use AdbPhp\Logcat\LogcatReader;
use AdbPhp\Models\FileEntry;
use AdbPhp\Models\Forward;
use AdbPhp\Models\FramebufferMeta;
use AdbPhp\Models\Reverse;
use AdbPhp\Models\StartActivityOptions;
use AdbPhp\Monkey\MonkeyClient;
use AdbPhp\ProcStat\ProcStat;
use AdbPhp\Protocol\AdbSocket;
use AdbPhp\Transfers\PullTransfer;
use AdbPhp\Transfers\PushTransfer;

/**
 * Per-device ADB operations.
 *
 * Obtain via `AdbClient::getDevice($serial)`.
 * Mirrors the `DeviceClient` from @devicefarmer/adbkit v3.3.8.
 *
 * PHP 8.3: typed constants, named arguments, #[Override].
 *
 * @since PHP 8.3
 */
class DeviceClient
{
    // -------------------------------------------------------------------------
    // Typed class constants (PHP 8.3)
    // -------------------------------------------------------------------------

    /** Default wait-for-device timeout in seconds. */
    public const int WAIT_DEVICE_TIMEOUT = 60;

    /** Default wait-boot-complete timeout in seconds. */
    public const int WAIT_BOOT_TIMEOUT   = 120;

    /** Default Monkey server port. */
    public const int DEFAULT_MONKEY_PORT = 1080;

    // -------------------------------------------------------------------------

    public function __construct(
        private readonly AdbClient $client,
        public readonly string     $serial,
    ) {}

    // =========================================================================
    // Device info
    // =========================================================================

    /**
     * Return the device serial number.
     *
     * Mirrors `device.getSerialNo()`.
     *
     * @throws AdbException
     */
    public function getSerialNo(): string
    {
        return $this->hostDeviceQuery('get-serialno');
    }

    /**
     * Return the device state ("device" | "offline" | "unauthorized" | …).
     *
     * Mirrors `device.getState()`.
     *
     * @throws AdbException
     */
    public function getState(): string
    {
        return $this->hostDeviceQuery('get-state');
    }

    /**
     * Return the USB/transport path.
     *
     * Mirrors `device.getDevicePath()`.
     *
     * @throws AdbException
     */
    public function getDevicePath(): string
    {
        return $this->hostDeviceQuery('get-devpath');
    }

    /**
     * Return all Android system properties as a key-value map.
     *
     * Mirrors `device.getProperties()`.
     *
     * @return array<string,string>
     *
     * @throws AdbException
     */
    public function getProperties(): array
    {
        $props = [];
        foreach (explode("\n", $this->shell('getprop')) as $line) {
            if (preg_match('/^\[(.+?)]:\s*\[(.*)]/', $line, $m)) {
                $props[$m[1]] = $m[2];
            }
        }
        return $props;
    }

    /**
     * Return hardware/software features as a key-value map.
     *
     * Mirrors `device.getFeatures()`.
     *
     * @return array<string,string>
     *
     * @throws AdbException
     */
    public function getFeatures(): array
    {
        $features = [];
        foreach (explode("\n", $this->shell('pm list features')) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'feature:')) {
                $parts              = explode('=', substr($line, 8), 2);
                $features[$parts[0]] = $parts[1] ?? '1';
            }
        }
        return $features;
    }

    /**
     * Return a list of installed package names.
     *
     * Mirrors `device.getPackages()`.
     *
     * @return list<string>
     *
     * @throws AdbException
     */
    public function getPackages(): array
    {
        $packages = [];
        foreach (explode("\n", $this->shell('pm list packages')) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'package:')) {
                $packages[] = substr($line, 8);
            }
        }
        return $packages;
    }

    /**
     * Return the DHCP IP address for the given network interface.
     *
     * Mirrors `device.getDHCPIpAddress([iface])`.
     *
     * @throws AdbException
     */
    public function getDHCPIpAddress(string $iface = 'wlan0'): ?string
    {
        $ip = trim($this->shell("getprop dhcp.{$iface}.ipaddress"));
        return $ip !== '' ? $ip : null;
    }

    // =========================================================================
    // Shell
    // =========================================================================

    /**
     * Execute a shell command and return stdout as a string.
     *
     * Mirrors `device.shell(command)`.
     *
     * @throws AdbException
     */
    public function shell(string $command): string
    {
        $sock = $this->openTransport();
        $sock->send("shell:{$command}");
        $sock->readStatus();
        $output = $sock->readAll();
        $sock->close();
        return $output;
    }

    // =========================================================================
    // App management
    // =========================================================================

    /**
     * Install an APK from the host filesystem.
     *
     * Mirrors `device.install(apk)`.
     * Pushes the APK to a temp path, calls pm install, then removes it.
     *
     * @throws AdbException
     */
    public function install(string $localApkPath): void
    {
        $sync   = $this->syncService();
        $remote = $sync->tempFile($localApkPath);
        $sync->pushFile($localApkPath, $remote);
        $sync->end();

        $this->installRemote($remote);
        $this->shell("rm -f {$remote}");
    }

    /**
     * Install an APK that is already on the device.
     *
     * Mirrors `device.installRemote(apk)`.
     *
     * @throws AdbException
     */
    public function installRemote(string $remotePath): void
    {
        $result = $this->shell("pm install -r {$remotePath}");
        if (!str_contains($result, 'Success')) {
            throw new AdbException("APK install failed: {$result}");
        }
    }

    /**
     * Check whether a package is installed.
     *
     * Mirrors `device.isInstalled(pkg)`.
     *
     * @throws AdbException
     */
    public function isInstalled(string $package): bool
    {
        return str_contains(
            $this->shell("pm list packages {$package}"),
            "package:{$package}",
        );
    }

    /**
     * Uninstall a package.
     *
     * Mirrors `device.uninstall(pkg)`.
     *
     * @throws AdbException
     */
    public function uninstall(string $package): void
    {
        $result = $this->shell("pm uninstall {$package}");
        if (!str_contains($result, 'Success') && !str_contains($result, 'DELETE_SUCCEEDED')) {
            throw new AdbException("Uninstall failed: {$result}");
        }
    }

    /**
     * Clear an app's data and cache.
     *
     * Mirrors `device.clear(pkg)`.
     *
     * @throws AdbException
     */
    public function clear(string $package): void
    {
        $result = $this->shell("pm clear {$package}");
        if (!str_contains($result, 'Success')) {
            throw new AdbException("Clear failed: {$result}");
        }
    }

    // =========================================================================
    // Activity / Service
    // =========================================================================

    /**
     * Start an Activity via `am start`.
     *
     * Mirrors `device.startActivity(options)`.
     *
     * @throws AdbException
     */
    public function startActivity(StartActivityOptions $opts): void
    {
        $out = $this->shell('am start' . $this->buildAmArgs($opts));
        if (str_contains($out, 'Error')) {
            throw new AdbException("startActivity failed: {$out}");
        }
    }

    /**
     * Start a Service via `am startservice`.
     *
     * Mirrors `device.startService(options)`.
     *
     * @throws AdbException
     */
    public function startService(StartActivityOptions $opts): void
    {
        $out = $this->shell('am startservice' . $this->buildAmArgs($opts));
        if (str_contains($out, 'Error')) {
            throw new AdbException("startService failed: {$out}");
        }
    }

    // =========================================================================
    // File transfer (high-level)
    // =========================================================================

    /**
     * Push a local file to the device.
     *
     * Mirrors `device.push(contents, path[, mode])`.
     *
     * @param callable|null $onProgress `function(int $bytesTransferred): void`
     *
     * @throws AdbException
     */
    public function push(
        string    $localPath,
        string    $remotePath,
        int       $mode = SyncService::DEFAULT_MODE,
        ?callable $onProgress = null,
    ): PushTransfer {
        $sync     = $this->syncService();
        $transfer = $sync->pushFile($localPath, $remotePath, $mode, $onProgress);
        $sync->end();
        return $transfer;
    }

    /**
     * Pull a file from the device.
     *
     * Mirrors `device.pull(path)`.
     *
     * @param callable|null $onProgress `function(int $bytesTransferred): void`
     *
     * @return array{transfer: PullTransfer, data: string}
     *
     * @throws AdbException
     */
    public function pull(string $remotePath, ?callable $onProgress = null): array
    {
        $sync   = $this->syncService();
        $result = $sync->pull($remotePath, $onProgress);
        $sync->end();
        return $result;
    }

    // =========================================================================
    // Filesystem
    // =========================================================================

    /**
     * Stat a remote path.
     *
     * Mirrors `device.stat(path)`.
     *
     * @throws AdbException
     */
    public function stat(string $remotePath): FileEntry
    {
        $sync  = $this->syncService();
        $entry = $sync->stat($remotePath);
        $sync->end();
        return $entry;
    }

    /**
     * List a remote directory.
     *
     * Mirrors `device.readdir(path)`.
     *
     * @return list<FileEntry>
     *
     * @throws AdbException
     */
    public function readdir(string $remotePath): array
    {
        $sync    = $this->syncService();
        $entries = $sync->readdir($remotePath);
        $sync->end();
        return $entries;
    }

    // =========================================================================
    // Port forwarding
    // =========================================================================

    /**
     * List active port-forward rules.
     *
     * Mirrors `device.listForwards()`.
     *
     * @return list<Forward>
     *
     * @throws AdbException
     */
    public function listForwards(): array
    {
        $sock = $this->openTransport();
        $sock->send('list-forward');
        $sock->readStatus();
        $raw  = $sock->readLengthPrefixed();
        $sock->close();

        $forwards = [];
        foreach (explode("\n", trim($raw)) as $line) {
            $parts = preg_split('/\s+/', $line) ?: [];
            if (count($parts) === 3) {
                $forwards[] = new Forward(
                    serial: $parts[0],
                    local:  $parts[1],
                    remote: $parts[2],
                );
            }
        }
        return $forwards;
    }

    /**
     * Create a port-forward rule.
     *
     * Mirrors `device.forward(local, remote)`.
     *
     * @param string $local  e.g. "tcp:8080"
     * @param string $remote e.g. "tcp:8080" or "localabstract:my_socket"
     *
     * @throws AdbException
     */
    public function forward(string $local, string $remote): void
    {
        $sock = $this->openTransport();
        $sock->send("forward:{$local};{$remote}");
        $sock->readStatus();
        $sock->readStatus();
        $sock->close();
    }

    /**
     * List active reverse port-forward rules.
     *
     * Mirrors `device.listReverses()`.
     *
     * @return list<Reverse>
     *
     * @throws AdbException
     */
    public function listReverses(): array
    {
        $sock = $this->openTransport();
        $sock->send('reverse:list-forward');
        $sock->readStatus();
        $sock->readStatus();
        $raw  = $sock->readLengthPrefixed();
        $sock->close();

        $reverses = [];
        foreach (explode("\n", trim($raw)) as $line) {
            $parts = preg_split('/\s+/', $line) ?: [];
            if (count($parts) >= 3) {
                $reverses[] = new Reverse(remote: $parts[1], local: $parts[2]);
            }
        }
        return $reverses;
    }

    /**
     * Create a reverse port-forward rule.
     *
     * Mirrors `device.reverse(remote, local)`.
     *
     * @throws AdbException
     */
    public function reverse(string $remote, string $local): void
    {
        $sock = $this->openTransport();
        $sock->send("reverse:forward:{$remote};{$local}");
        $sock->readStatus();
        $sock->readStatus();
        $sock->close();
    }

    // =========================================================================
    // TCP / USB switching
    // =========================================================================

    /**
     * Restart adbd in TCP/IP mode on the given port.
     *
     * Mirrors `device.tcpip(port)`.
     *
     * @throws AdbException
     */
    public function tcpip(int $port = 5555): void
    {
        $this->shell(
            "setprop service.adb.tcp.port {$port} && stop adbd && start adbd",
        );
    }

    /**
     * Restart adbd in USB mode.
     *
     * Mirrors `device.usb()`.
     *
     * @throws AdbException
     */
    public function usb(): void
    {
        $this->shell("setprop service.adb.tcp.port '' && stop adbd && start adbd");
    }

    // =========================================================================
    // Root / Remount
    // =========================================================================

    /**
     * Restart adbd as root.
     *
     * Mirrors `device.root()`.
     *
     * @throws AdbException
     */
    public function root(): void
    {
        $sock = $this->openTransport();
        $sock->send('root:');
        $sock->readStatus();
        $resp = $sock->readAll();
        $sock->close();

        if (str_contains($resp, 'cannot run as root')) {
            throw new AdbException("Root not available: {$resp}");
        }
    }

    /**
     * Remount /system as read-write (requires root).
     *
     * Mirrors `device.remount()`.
     *
     * @throws AdbException
     */
    public function remount(): void
    {
        $sock = $this->openTransport();
        $sock->send('remount:');
        $sock->readStatus();
        $sock->readAll();
        $sock->close();
    }

    // =========================================================================
    // Reboot
    // =========================================================================

    /**
     * Reboot the device.
     *
     * Mirrors `device.reboot()`.
     *
     * @param string $mode "" = OS | "bootloader" | "recovery" | "fastboot"
     *
     * @throws AdbException
     */
    public function reboot(string $mode = ''): void
    {
        $cmd = $mode !== '' ? "reboot {$mode}" : 'reboot';
        $this->shell($cmd);
    }

    // =========================================================================
    // Screenshot / Framebuffer
    // =========================================================================

    /**
     * Take a screenshot and return PNG bytes.
     *
     * Mirrors `device.screencap()`.
     *
     * @throws AdbException
     */
    public function screencap(): string
    {
        $sock = $this->openTransport();
        $sock->send('shell:screencap -p');
        $sock->readStatus();
        $png = $sock->readAll();
        $sock->close();
        return $png;
    }

    /**
     * Read the framebuffer and return raw pixel data + metadata.
     *
     * Mirrors `device.framebuffer([format])`.
     *
     * @return array{meta: FramebufferMeta, data: string}
     *
     * @throws AdbException
     */
    public function framebuffer(string $format = 'rgba'): array
    {
        $sock = $this->openTransport();
        $sock->send('framebuffer:');
        $sock->readStatus();

        /** @var array{1: int} $u */
        $u       = unpack('V', $sock->read(4));
        $version = $u[1];

        // Header layout differs by version
        $headerData = $sock->read(48);
        /** @var array<string,int> $h */
        $h = unpack(
            'Vbpp/Vsize/Vwidth/Vheight/VredOffset/VredLength/VblueOffset/VblueLength/VgreenOffset/VgreenLength/ValphaOffset/ValphaLength',
            substr($headerData, 0, 48),
        );

        $meta = new FramebufferMeta(
            version:     $version,
            bpp:         $h['bpp'],
            size:        $h['size'],
            width:       $h['width'],
            height:      $h['height'],
            redOffset:   $h['redOffset'],
            redLength:   $h['redLength'],
            blueOffset:  $h['blueOffset'],
            blueLength:  $h['blueLength'],
            greenOffset: $h['greenOffset'],
            greenLength: $h['greenLength'],
            alphaOffset: $h['alphaOffset'],
            alphaLength: $h['alphaLength'],
            format:      $format,
        );

        $data = $sock->readAll();
        $sock->close();

        return ['meta' => $meta, 'data' => $data];
    }

    // =========================================================================
    // Logcat
    // =========================================================================

    /**
     * Open a binary logcat stream.
     *
     * Mirrors `device.openLogcat([options])`.
     *
     * @param array{clear?: bool, format?: string} $options
     *
     * @throws AdbException
     */
    public function openLogcat(array $options = []): LogcatReader
    {
        if ($options['clear'] ?? false) {
            $this->shell('logcat -c');
        }

        $sock = $this->openTransport();
        $sock->send('shell:logcat -B');
        $sock->readStatus();
        return new LogcatReader($sock);
    }

    /**
     * Open a named log buffer (e.g. "events", "radio", "main").
     *
     * Mirrors `device.openLog(name)`.
     *
     * @throws AdbException
     */
    public function openLog(string $name): LogcatReader
    {
        $sock = $this->openTransport();
        $sock->send("shell:logcat -b {$name} -B");
        $sock->readStatus();
        return new LogcatReader($sock);
    }

    // =========================================================================
    // Monkey
    // =========================================================================

    /**
     * Start the Monkey server on the device, forward the port, and return a
     * connected MonkeyClient.
     *
     * Mirrors `device.openMonkey([port])`.
     *
     * @throws AdbException
     */
    public function openMonkey(int $port = self::DEFAULT_MONKEY_PORT): MonkeyClient
    {
        $this->shell("monkey --port {$port} -v &");
        sleep(1);
        $this->forward("tcp:{$port}", "tcp:{$port}");

        $monkey = new MonkeyClient(host: '127.0.0.1', port: $port);
        $monkey->connect();
        return $monkey;
    }

    // =========================================================================
    // ProcStat
    // =========================================================================

    /**
     * Read and parse /proc/stat from the device.
     *
     * Mirrors `device.openProcStat()`.
     *
     * @throws AdbException
     */
    public function openProcStat(): ProcStat
    {
        return ProcStat::parse($this->shell('cat /proc/stat'));
    }

    // =========================================================================
    // Direct socket access
    // =========================================================================

    /**
     * Open a TCP passthrough to device:{host}:{port} via ADB.
     *
     * Mirrors `device.openTcp(port[, host])`.
     *
     * @throws AdbException
     */
    public function openTcp(int $port, string $host = 'localhost'): AdbSocket
    {
        $sock = $this->openTransport();
        $sock->send("tcp:{$port}:{$host}");
        $sock->readStatus();
        return $sock;
    }

    /**
     * Open a local abstract UNIX socket on the device.
     *
     * Mirrors `device.openLocal(path)`.
     *
     * @throws AdbException
     */
    public function openLocal(string $path): AdbSocket
    {
        $sock = $this->openTransport();
        $sock->send("localabstract:{$path}");
        $sock->readStatus();
        return $sock;
    }

    // =========================================================================
    // JDWP
    // =========================================================================

    /**
     * Return PIDs currently exposing JDWP.
     *
     * Mirrors `device.trackJdwp()`.
     *
     * @return list<int>
     *
     * @throws AdbException
     */
    public function trackJdwp(): array
    {
        $sock = $this->openTransport();
        $sock->send('track-jdwp');
        $sock->readStatus();
        $raw  = $sock->readLengthPrefixed();
        $sock->close();

        return array_values(
            array_filter(
                array_map('intval', explode("\n", trim($raw))),
                static fn(int $pid): bool => $pid > 0,
            ),
        );
    }

    // =========================================================================
    // Wait
    // =========================================================================

    /**
     * Block until the device is online.
     *
     * Mirrors `device.waitForDevice()`.
     *
     * @throws AdbException
     */
    public function waitForDevice(int $timeoutSeconds = self::WAIT_DEVICE_TIMEOUT): void
    {
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            try {
                if ($this->getState() === 'device') {
                    return;
                }
            } catch (\Throwable) {
                // Not yet online
            }
            sleep(1);
        }

        throw new AdbException("Timed out waiting for device {$this->serial}.");
    }

    /**
     * Block until the device has finished booting.
     *
     * Mirrors `device.waitBootComplete()`.
     *
     * @throws AdbException
     */
    public function waitBootComplete(int $timeoutSeconds = self::WAIT_BOOT_TIMEOUT): void
    {
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            try {
                if (trim($this->shell('getprop sys.boot_completed')) === '1') {
                    return;
                }
            } catch (\Throwable) {}
            sleep(2);
        }

        throw new AdbException("Timed out waiting for boot on {$this->serial}.");
    }

    // =========================================================================
    // Sync / Attach
    // =========================================================================

    /**
     * Open a SYNC protocol session for direct file operations.
     *
     * Mirrors `device.syncService()`.
     *
     * @throws AdbException
     */
    public function syncService(): SyncService
    {
        $sock = $this->openTransport();
        $sock->send('sync:');
        $sock->readStatus();
        return new SyncService($sock);
    }

    /**
     * Attach this device to the ADB server.
     *
     * Mirrors `device.attach()`.
     *
     * @throws AdbException
     */
    public function attach(): void
    {
        $sock = $this->client->openSocket();
        $sock->send("host:attach:{$this->serial}");
        $sock->readStatus();
        $sock->close();
    }

    /**
     * Detach this device from the ADB server.
     *
     * Mirrors `device.detach()`.
     *
     * @throws AdbException
     */
    public function detach(): void
    {
        $sock = $this->client->openSocket();
        $sock->send("host:detach:{$this->serial}");
        $sock->readStatus();
        $sock->close();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Open a transport-switched socket for this device.
     * All subsequent commands on the returned socket are device-scoped.
     *
     * @throws AdbException
     */
    public function openTransport(): AdbSocket
    {
        $sock = $this->client->openSocket();
        $sock->send("host:transport:{$this->serial}");
        $sock->readStatus();
        return $sock;
    }

    /**
     * Run a simple `host-serial:<serial>:<cmd>` query and return the response.
     *
     * @throws AdbException
     */
    private function hostDeviceQuery(string $cmd): string
    {
        $sock   = $this->client->openSocket();
        $sock->send("host-serial:{$this->serial}:{$cmd}");
        $sock->readStatus();
        $result = $sock->readLengthPrefixed();
        $sock->close();
        return trim($result);
    }

    /**
     * Build `am start` / `am startservice` argument string from options.
     */
    private function buildAmArgs(StartActivityOptions $opts): string
    {
        $args = '';
        if ($opts->wait)              $args .= ' -W';
        if ($opts->debug)             $args .= ' -D';
        if ($opts->force)             $args .= ' -S';
        if ($opts->action !== null)   $args .= " -a {$opts->action}";
        if ($opts->data !== null)     $args .= ' -d ' . escapeshellarg($opts->data);
        if ($opts->mimeType !== null) $args .= " -t {$opts->mimeType}";
        if ($opts->category !== null) $args .= " -c {$opts->category}";
        if ($opts->component !== null) $args .= " -n {$opts->component}";
        if ($opts->flags !== null)    $args .= " -f {$opts->flags}";
        foreach ($opts->extras as $k => $v) {
            $args .= " -e {$k} " . escapeshellarg($v);
        }
        return $args;
    }
}
