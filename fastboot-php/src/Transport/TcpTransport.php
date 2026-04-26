<?php

declare(strict_types=1);

namespace FastbootPhp\Transport;

use FastbootPhp\Contracts\UsbTransportInterface;
use FastbootPhp\UsbError;

/**
 * USB transport over a raw TCP socket.
 *
 * Useful when the Android device is accessed through:
 *   `adb forward tcp:<port> localabstract:fastboot`
 *
 * PHP 8.3: #[Override] on all interface methods.
 *
 * @since PHP 8.3
 */
final class TcpTransport implements UsbTransportInterface
{
    /** @var resource|null */
    private mixed $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly int    $timeoutMs = 5000,
    ) {}

    // -------------------------------------------------------------------------
    // UsbTransportInterface
    // -------------------------------------------------------------------------

    #[Override]
    public function open(): void
    {
        $errno  = 0;
        $errstr = '';

        $socket = fsockopen(
            hostname: $this->host,
            port:     $this->port,
            error_code: $errno,
            error_message: $errstr,
            timeout: $this->timeoutMs / 1000.0,
        );

        if ($socket === false) {
            throw new UsbError(
                "TCP connect to {$this->host}:{$this->port} failed: [{$errno}] {$errstr}",
            );
        }

        stream_set_timeout($socket, 0, $this->timeoutMs * 1000);
        stream_set_blocking($socket, true);
        $this->socket = $socket;
    }

    #[Override]
    public function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    #[Override]
    public function isConnected(): bool
    {
        return $this->socket !== null && !feof($this->socket);
    }

    #[Override]
    public function transferOut(string $data): void
    {
        $this->assertOpen();
        $written = fwrite($this->socket, $data);
        if ($written === false || $written !== strlen($data)) {
            throw new UsbError('TCP write failed (short write).');
        }
    }

    #[Override]
    public function transferIn(int $maxLength): string
    {
        $this->assertOpen();
        $data = fread($this->socket, $maxLength);
        if ($data === false) {
            $meta   = stream_get_meta_data($this->socket);
            $reason = $meta['timed_out'] ? 'timed out' : 'read error';
            throw new UsbError("TCP read failed ({$reason}).");
        }
        return $data;
    }

    #[Override]
    public function reset(): void
    {
        $this->close();
        $this->open();
    }

    // -------------------------------------------------------------------------

    private function assertOpen(): void
    {
        if ($this->socket === null) {
            throw new UsbError('TCP transport is not open. Call open() first.');
        }
    }
}
