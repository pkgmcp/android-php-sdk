<?php

declare(strict_types=1);

namespace AdbPhp\Logcat;

use AdbPhp\Exceptions\ConnectionException;
use AdbPhp\Exceptions\ProtocolException;
use AdbPhp\Protocol\AdbSocket;

/**
 * Reads and parses the Android binary logcat stream.
 *
 * Binary format (Android 2.3+ logger_entry v3):
 *   uint16  payload_length
 *   uint16  header_size     (usually 0x18 = 24)
 *   int32   pid
 *   int32   tid
 *   int32   sec
 *   int32   nsec
 *   [uint32 lid  — only if header_size > 20]
 *   [uint32 uid  — only if header_size > 24]
 *   <payload>
 *     uint8  priority
 *     char[] tag \0
 *     char[] message \0
 *
 * PHP 8.3: typed constants, readonly class properties.
 *
 * @since PHP 8.3
 */
final class LogcatReader
{
    /** Minimum logger_entry header size. */
    private const int MIN_HEADER = 20;

    public function __construct(private readonly AdbSocket $socket) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Read and return the next logcat entry.
     * Returns null when the stream ends.
     *
     * @throws ProtocolException
     * @throws ConnectionException
     */
    public function read(): ?LogcatEntry
    {
        if (!$this->socket->isConnected()) {
            return null;
        }

        // 1. payload_length (uint16 LE)
        try {
            $raw = $this->socket->read(2);
        } catch (ConnectionException) {
            return null;
        }

        /** @var array{1: int} $u */
        $u          = unpack('v', $raw);
        $payloadLen = $u[1];

        // 2. header_size (uint16 LE)
        $raw        = $this->socket->read(2);
        $u          = unpack('v', $raw);
        $headerSize = $u[1];

        // 3. Rest of header (after the first 4 bytes already read)
        $headerRemaining = max(0, $headerSize - 4);
        $headerData      = $this->socket->read($headerRemaining);

        // pid, tid, sec, nsec are the first 16 bytes of headerData
        if (strlen($headerData) < 16) {
            return null;
        }

        /** @var array{1:int,2:int,3:int,4:int} $fields */
        $fields = unpack('V4', substr($headerData, 0, 16));
        [$pid, $tid, $sec] = [$fields[1], $fields[2], $fields[3]];

        // 4. Payload
        $payload  = $this->socket->read($payloadLen);
        $priority = ord($payload[0]);
        $rest     = substr($payload, 1);
        $nullPos  = strpos($rest, "\x00");

        if ($nullPos === false) {
            return null;
        }

        $tag     = substr($rest, 0, $nullPos);
        $message = rtrim(substr($rest, $nullPos + 1), "\x00");

        return new LogcatEntry(
            date:     $sec,
            pid:      $pid,
            tid:      $tid,
            priority: $priority,
            tag:      $tag,
            message:  $message,
        );
    }

    /**
     * Read all available entries and return them as an array.
     * Blocks until the stream is closed.
     *
     * @return list<LogcatEntry>
     */
    public function readAll(): array
    {
        $entries = [];
        while (($entry = $this->read()) !== null) {
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * Stream entries to a callback until the stream ends or callback returns false.
     *
     * @param callable $callback `function(LogcatEntry $entry): bool|void` — return false to stop
     */
    public function stream(callable $callback): void
    {
        while (($entry = $this->read()) !== null) {
            if ($callback($entry) === false) {
                break;
            }
        }
    }

    /** Close the logcat socket. */
    public function end(): void
    {
        $this->socket->close();
    }
}
