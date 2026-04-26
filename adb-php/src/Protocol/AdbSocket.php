<?php

declare(strict_types=1);

namespace AdbPhp\Protocol;

use AdbPhp\Exceptions\ConnectionException;
use AdbPhp\Exceptions\ProtocolException;

/**
 * Raw TCP socket to the ADB server with ADB wire-protocol framing.
 *
 * ADB wire protocol (host side):
 *   Send  : 4-hex-digit length prefix + payload  e.g. "000Chost:version"
 *   Recv  : 4-byte status "OKAY" or "FAIL"
 *   OKAY  : read requested data (length-prefixed or raw stream)
 *   FAIL  : 4-hex-digit length + error message
 *
 * PHP 8.3: named arguments in constructor calls, typed constants, #[Override].
 *
 * @since PHP 8.3
 */
final class AdbSocket
{
    /** ADB wire-protocol OKAY status. */
    public const string STATUS_OKAY = 'OKAY';

    /** ADB wire-protocol FAIL status. */
    public const string STATUS_FAIL = 'FAIL';

    /** Maximum single fread() chunk size. */
    private const int READ_CHUNK = 65536;

    /** @var resource|false */
    private mixed $socket = false;

    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly int    $timeoutMs,
    ) {}

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Open a TCP connection to the ADB server.
     *
     * @throws ConnectionException
     */
    public function connect(): void
    {
        $errno  = 0;
        $errstr = '';

        $sock = @fsockopen(
            hostname:      $this->host,
            port:          $this->port,
            error_code:    $errno,
            error_message: $errstr,
            timeout:       $this->timeoutMs / 1000.0,
        );

        if ($sock === false) {
            throw new ConnectionException(
                "Cannot connect to ADB server at {$this->host}:{$this->port} — [{$errno}] {$errstr}",
            );
        }

        stream_set_timeout($sock, 0, $this->timeoutMs * 1000);
        stream_set_blocking($sock, true);
        $this->socket = $sock;
    }

    /** Close the socket. */
    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = false;
        }
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Write a length-prefixed ADB request.
     *
     * @throws ConnectionException
     */
    public function send(string $payload): void
    {
        $msg     = sprintf('%04X', strlen($payload)) . $payload;
        $written = fwrite($this->socket(), $msg);

        if ($written !== strlen($msg)) {
            throw new ConnectionException('Short write to ADB socket.');
        }
    }

    /**
     * Write raw bytes (used during SYNC protocol transfers).
     *
     * @throws ConnectionException
     */
    public function write(string $data): void
    {
        $remaining = strlen($data);
        $offset    = 0;

        while ($remaining > 0) {
            $w = fwrite($this->socket(), substr($data, $offset));
            if ($w === false || $w === 0) {
                throw new ConnectionException('Write to ADB socket failed.');
            }
            $offset    += $w;
            $remaining -= $w;
        }
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Read exactly $length bytes, blocking until all are received.
     *
     * @throws ConnectionException
     */
    public function read(int $length): string
    {
        $buf = '';

        while (strlen($buf) < $length) {
            $chunk = fread($this->socket(), $length - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket());
                $why  = $meta['timed_out'] ? 'timed out' : 'connection closed';
                throw new ConnectionException("ADB socket read failed ({$why}).");
            }
            $buf .= $chunk;
        }

        return $buf;
    }

    /**
     * Read 4-byte status and assert it equals `OKAY`.
     *
     * @throws ProtocolException  On FAIL or unknown status token.
     * @throws ConnectionException
     */
    public function readStatus(): void
    {
        $status = $this->read(4);

        if ($status === self::STATUS_OKAY) {
            return;
        }

        if ($status === self::STATUS_FAIL) {
            $len = (int) hexdec($this->read(4));
            $msg = $this->read($len);
            throw new ProtocolException("ADB error: {$msg}");
        }

        throw new ProtocolException("Unexpected ADB status: {$status}");
    }

    /**
     * Read a 4-hex-digit length-prefixed response body.
     *
     * @throws ProtocolException
     * @throws ConnectionException
     */
    public function readLengthPrefixed(): string
    {
        $hexLen = $this->read(4);
        $len    = (int) hexdec($hexLen);
        return $len > 0 ? $this->read($len) : '';
    }

    /**
     * Read until the socket closes (shell / logcat streams).
     *
     * @throws ConnectionException
     */
    public function readAll(): string
    {
        $buf = '';

        while (!feof($this->socket())) {
            $chunk = fread($this->socket(), self::READ_CHUNK);
            if ($chunk === false) {
                break;
            }
            $buf .= $chunk;
        }

        return $buf;
    }

    /**
     * Read one line (terminated by `\n`).
     *
     * @throws ConnectionException
     */
    public function readLine(): string
    {
        $line = fgets($this->socket());
        if ($line === false) {
            throw new ConnectionException('ADB socket closed unexpectedly.');
        }
        return rtrim($line, "\r\n");
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /** @return resource */
    private function socket(): mixed
    {
        if (!is_resource($this->socket)) {
            throw new ConnectionException('ADB socket is not open.');
        }
        return $this->socket;
    }
}
