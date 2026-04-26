<?php

declare(strict_types=1);

namespace FastbootPhp\Transport;

use FastbootPhp\Contracts\UsbTransportInterface;
use FastbootPhp\UsbError;

/**
 * USB transport backed by a raw Linux USB device character node.
 *
 * Opens `/dev/bus/usb/<bus>/<device>` and performs bulk transfers via
 * native `fread` / `fwrite`. Requires udev rules — see `docs/INSTALL.md`.
 *
 * PHP 8.3: #[Override] on all interface methods.
 *
 * @since PHP 8.3
 */
final class LibUsbTransport implements UsbTransportInterface
{
    /** @var resource|null */
    private mixed $handle = null;

    public function __construct(
        private readonly string $devicePath,
        private readonly int    $timeoutMs = 5000,
    ) {}

    // -------------------------------------------------------------------------
    // UsbTransportInterface
    // -------------------------------------------------------------------------

    #[Override]
    public function open(): void
    {
        if (!file_exists($this->devicePath)) {
            throw new UsbError("USB device not found: {$this->devicePath}");
        }

        $handle = fopen($this->devicePath, 'r+b');
        if ($handle === false) {
            throw new UsbError("Failed to open USB device: {$this->devicePath}");
        }

        stream_set_timeout($handle, 0, $this->timeoutMs * 1000);
        stream_set_blocking($handle, true);
        $this->handle = $handle;
    }

    #[Override]
    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    #[Override]
    public function isConnected(): bool
    {
        return $this->handle !== null && !feof($this->handle);
    }

    #[Override]
    public function transferOut(string $data): void
    {
        $this->assertOpen();
        $written = fwrite($this->handle, $data);
        if ($written === false || $written !== strlen($data)) {
            throw new UsbError('USB bulk-OUT transfer failed (short write).');
        }
    }

    #[Override]
    public function transferIn(int $maxLength): string
    {
        $this->assertOpen();
        $data = fread($this->handle, $maxLength);
        if ($data === false) {
            $meta   = stream_get_meta_data($this->handle);
            $reason = $meta['timed_out'] ? 'timed out' : 'read error';
            throw new UsbError("USB bulk-IN transfer failed ({$reason}).");
        }
        return $data;
    }

    #[Override]
    public function reset(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
            $this->open();
        }
    }

    // -------------------------------------------------------------------------

    private function assertOpen(): void
    {
        if ($this->handle === null) {
            throw new UsbError('USB transport is not open. Call open() first.');
        }
    }
}
