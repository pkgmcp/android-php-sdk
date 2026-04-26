<?php

declare(strict_types=1);

namespace AdbPhp;

use AdbPhp\Exceptions\AdbException;
use AdbPhp\Exceptions\ConnectionException;
use AdbPhp\Models\Device;
use AdbPhp\Models\DeviceWithPath;
use AdbPhp\Protocol\AdbSocket;

/**
 * ADB server client — the entry point for all ADB operations.
 *
 * Mirrors the top-level `Client` from @devicefarmer/adbkit v3.
 *
 * PHP 8.3: typed class constants, named arguments, #[Override].
 *
 * ## Quick Start
 * ```php
 * $adb    = AdbClient::create();
 * $device = $adb->getDevice('emulator-5554');
 * echo $device->shell('uname -a');
 * ```
 *
 * @since PHP 8.3
 */
class AdbClient
{
    // -------------------------------------------------------------------------
    // Typed class constants (PHP 8.3)
    // -------------------------------------------------------------------------

    /** Default ADB server host. */
    public const string DEFAULT_HOST = '127.0.0.1';

    /** Default ADB server port. */
    public const int DEFAULT_PORT = 5037;

    // -------------------------------------------------------------------------

    private function __construct(
        private readonly string $host      = self::DEFAULT_HOST,
        private readonly int    $port      = self::DEFAULT_PORT,
        private readonly int    $timeoutMs = 5000,
    ) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Create a new AdbClient.
     *
     * Mirrors `Adb.createClient([options])`.
     */
    public static function create(
        string $host      = self::DEFAULT_HOST,
        int    $port      = self::DEFAULT_PORT,
        int    $timeoutMs = 5000,
    ): static {
        return new static($host, $port, $timeoutMs);
    }

    // -------------------------------------------------------------------------
    // Server management
    // -------------------------------------------------------------------------

    /**
     * Return the ADB server version number.
     *
     * Mirrors `client.version()`.
     *
     * @throws AdbException
     */
    public function version(): int
    {
        $sock = $this->openSocket();
        $sock->send('host:version');
        $sock->readStatus();
        $raw = $sock->readLengthPrefixed();
        $sock->close();
        return (int) hexdec(trim($raw));
    }

    /**
     * Kill the ADB server.
     *
     * Mirrors `client.kill()`.
     *
     * @throws AdbException
     */
    public function kill(): void
    {
        $sock = $this->openSocket();
        $sock->send('host:kill');
        $sock->close();
    }

    /**
     * Connect to a remote ADB device (wireless ADB).
     *
     * Mirrors `client.connect(host[, port])`.
     *
     * @return string "connected to host:port" or "already connected to …"
     *
     * @throws AdbException
     */
    public function connect(string $host, int $port = 5555): string
    {
        $sock = $this->openSocket();
        $sock->send("host:connect:{$host}:{$port}");
        $sock->readStatus();
        $result = $sock->readLengthPrefixed();
        $sock->close();
        return trim($result);
    }

    /**
     * Disconnect a remote ADB device.
     *
     * Mirrors `client.disconnect(host[, port])`.
     *
     * @throws AdbException
     */
    public function disconnect(string $host, int $port = 5555): string
    {
        $sock = $this->openSocket();
        $sock->send("host:disconnect:{$host}:{$port}");
        $sock->readStatus();
        $result = $sock->readLengthPrefixed();
        $sock->close();
        return trim($result);
    }

    // -------------------------------------------------------------------------
    // Device listing
    // -------------------------------------------------------------------------

    /**
     * List all connected devices.
     *
     * Mirrors `client.listDevices()`.
     *
     * @return list<Device>
     *
     * @throws AdbException
     */
    public function listDevices(): array
    {
        $sock = $this->openSocket();
        $sock->send('host:devices');
        $sock->readStatus();
        $raw  = $sock->readLengthPrefixed();
        $sock->close();
        return $this->parseDeviceList($raw);
    }

    /**
     * List all connected devices with USB paths.
     *
     * Mirrors `client.listDevicesWithPaths()`.
     *
     * @return list<DeviceWithPath>
     *
     * @throws AdbException
     */
    public function listDevicesWithPaths(): array
    {
        $sock = $this->openSocket();
        $sock->send('host:devices-l');
        $sock->readStatus();
        $raw  = $sock->readLengthPrefixed();
        $sock->close();
        return $this->parseDeviceListWithPaths($raw);
    }

    /**
     * Track device connect/disconnect events as a PHP Generator.
     *
     * Mirrors `client.trackDevices()`.
     *
     * Each yielded value: `['type' => 'add'|'remove'|'change', 'device' => Device]`
     *
     * ```php
     * foreach ($adb->trackDevices() as $event) {
     *     echo $event['type'] . ': ' . $event['device']->id . "\n";
     * }
     * ```
     *
     * @return \Generator<int, array{type: string, device: Device}>
     *
     * @throws AdbException
     */
    public function trackDevices(): \Generator
    {
        $sock = $this->openSocket();
        $sock->send('host:track-devices');
        $sock->readStatus();

        /** @var array<string, Device> $previous */
        $previous = [];

        while ($sock->isConnected()) {
            $raw = $sock->readLengthPrefixed();
            if ($raw === '') {
                continue;
            }

            /** @var array<string, Device> $current */
            $current = [];
            foreach ($this->parseDeviceList($raw) as $device) {
                $current[$device->id] = $device;
            }

            foreach ($current as $id => $device) {
                if (!isset($previous[$id])) {
                    yield ['type' => 'add', 'device' => $device];
                } elseif ($previous[$id]->type !== $device->type) {
                    yield ['type' => 'change', 'device' => $device];
                }
            }

            foreach ($previous as $id => $device) {
                if (!isset($current[$id])) {
                    yield ['type' => 'remove', 'device' => $device];
                }
            }

            $previous = $current;
        }
    }

    // -------------------------------------------------------------------------
    // Device accessor
    // -------------------------------------------------------------------------

    /**
     * Get a DeviceClient for the given serial number.
     *
     * @param string $serial Device serial or "host:port" for wireless ADB.
     */
    public function getDevice(string $serial): DeviceClient
    {
        return new DeviceClient($this, $serial);
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /**
     * Parse an Android RSA public key string.
     *
     * Mirrors `adb.util.parsePublicKey(androidKey)`.
     *
     * @return array{key: string, comment: string, fingerprint: string}
     */
    public static function parsePublicKey(string $androidKey): array
    {
        $parts = explode(' ', trim($androidKey), 3);
        return [
            'key'         => $parts[0] ?? '',
            'comment'     => $parts[2] ?? '',
            'fingerprint' => md5(base64_decode($parts[0] ?? '')),
        ];
    }

    /**
     * Open a raw TCP socket to the ADB server.
     *
     * Exposed for advanced and internal use (e.g. DeviceClient).
     *
     * @throws ConnectionException
     */
    public function openSocket(): AdbSocket
    {
        $sock = new AdbSocket($this->host, $this->port, $this->timeoutMs);
        $sock->connect();
        return $sock;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return list<Device> */
    private function parseDeviceList(string $raw): array
    {
        $devices = [];
        foreach (explode("\n", trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 2) ?: [];
            if (count($parts) === 2) {
                $devices[] = new Device(id: $parts[0], type: $parts[1]);
            }
        }
        return $devices;
    }

    /** @return list<DeviceWithPath> */
    private function parseDeviceListWithPaths(string $raw): array
    {
        $devices = [];
        foreach (explode("\n", trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $id   = $parts[0];
            $type = $parts[1];
            $path = '';

            foreach (array_slice($parts, 2) as $kv) {
                if (str_starts_with($kv, 'usb:')) {
                    $path = $kv;
                    break;
                }
            }

            $devices[] = new DeviceWithPath(id: $id, type: $type, path: $path);
        }
        return $devices;
    }
}
