<?php

declare(strict_types=1);

namespace AdbPhp;

use AdbPhp\Exceptions\AdbException;
use AdbPhp\Exceptions\ProtocolException;
use AdbPhp\Models\FileEntry;
use AdbPhp\Protocol\AdbSocket;
use AdbPhp\Transfers\PullTransfer;
use AdbPhp\Transfers\PushTransfer;

/**
 * SYNC protocol service — file push, pull, stat, and readdir.
 *
 * Mirrors `Sync` / `SyncService` from adbkit.
 *
 * SYNC wire format:
 *   4-byte ASCII command ID  +  4-byte LE uint32 argument/length
 *
 * PHP 8.3: typed class constants, named arguments.
 *
 * @since PHP 8.3
 */
final class SyncService
{
    // -------------------------------------------------------------------------
    // Typed SYNC command IDs (PHP 8.3)
    // -------------------------------------------------------------------------

    private const string CMD_STAT = 'STAT';
    private const string CMD_LIST = 'LIST';
    private const string CMD_SEND = 'SEND';
    private const string CMD_RECV = 'RECV';
    private const string CMD_QUIT = 'QUIT';
    private const string CMD_DENT = 'DENT';
    private const string CMD_DONE = 'DONE';
    private const string CMD_DATA = 'DATA';
    private const string CMD_OKAY = 'OKAY';
    private const string CMD_FAIL = 'FAIL';

    /** Default file permission for pushed files. */
    public const int DEFAULT_MODE = 0o644;

    /** Maximum DATA chunk (64 KiB). */
    private const int MAX_DATA = 65536;

    // -------------------------------------------------------------------------

    public function __construct(private readonly AdbSocket $socket) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Stat a remote file.
     *
     * Mirrors `sync.stat(path)`.
     *
     * @throws AdbException
     */
    public function stat(string $path): FileEntry
    {
        $this->sendCmd(self::CMD_STAT, $path);

        $id = $this->readId();
        if ($id !== self::CMD_STAT) {
            throw new ProtocolException("Expected STAT response, got: {$id}");
        }

        $mode  = $this->readUint32LE();
        $size  = $this->readUint32LE();
        $mtime = $this->readUint32LE();

        return new FileEntry(
            name:  basename($path),
            mode:  $mode,
            size:  $size,
            mtime: $mtime,
        );
    }

    /**
     * List a remote directory.
     *
     * Mirrors `sync.readdir(path)`.
     *
     * @return list<FileEntry>
     *
     * @throws AdbException
     */
    public function readdir(string $path): array
    {
        $this->sendCmd(self::CMD_LIST, $path);
        $entries = [];

        while (true) {
            $id = $this->readId();

            if ($id === self::CMD_DONE) {
                $this->socket->read(12); // trailing 3 × uint32
                break;
            }

            if ($id !== self::CMD_DENT) {
                throw new ProtocolException("Expected DENT, got: {$id}");
            }

            $mode  = $this->readUint32LE();
            $size  = $this->readUint32LE();
            $mtime = $this->readUint32LE();
            $len   = $this->readUint32LE();
            $name  = $this->socket->read($len);

            if ($name !== '.' && $name !== '..') {
                $entries[] = new FileEntry(
                    name: $name, mode: $mode, size: $size, mtime: $mtime,
                );
            }
        }

        return $entries;
    }

    /**
     * Push a local file to the device.
     *
     * Mirrors `sync.pushFile(file, path[, mode])`.
     *
     * @param callable|null $onProgress `function(int $bytesTransferred): void`
     *
     * @throws AdbException
     */
    public function pushFile(
        string    $localPath,
        string    $remotePath,
        int       $mode = self::DEFAULT_MODE,
        ?callable $onProgress = null,
    ): PushTransfer {
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new AdbException("Cannot open local file: {$localPath}");
        }
        try {
            return $this->pushStream($stream, $remotePath, $mode, $onProgress);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Push a string as file content to the device.
     *
     * Mirrors `sync.push(contents, path[, mode])`.
     *
     * @param callable|null $onProgress `function(int $bytesTransferred): void`
     *
     * @throws AdbException
     */
    public function push(
        string    $contents,
        string    $remotePath,
        int       $mode = self::DEFAULT_MODE,
        ?callable $onProgress = null,
    ): PushTransfer {
        $tmp = tmpfile();
        fwrite($tmp, $contents);
        rewind($tmp);
        return $this->pushStream($tmp, $remotePath, $mode, $onProgress);
    }

    /**
     * Push a PHP stream to the device.
     *
     * Mirrors `sync.pushStream(stream, path[, mode])`.
     *
     * @param resource      $stream
     * @param callable|null $onProgress `function(int $bytesTransferred): void`
     *
     * @throws AdbException
     */
    public function pushStream(
        mixed     $stream,
        string    $remotePath,
        int       $mode = self::DEFAULT_MODE,
        ?callable $onProgress = null,
    ): PushTransfer {
        $transfer = new PushTransfer();
        if ($onProgress !== null) {
            $transfer->onProgress($onProgress);
        }

        $this->sendCmd(self::CMD_SEND, "{$remotePath},{$mode}");

        while (!feof($stream)) {
            if ($transfer->isCancelled()) {
                break;
            }
            $chunk = fread($stream, self::MAX_DATA);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $len = strlen($chunk);
            $this->socket->write(self::CMD_DATA . pack('V', $len) . $chunk);
            $transfer->addBytes($len);
        }

        $this->socket->write(self::CMD_DONE . pack('V', time()));

        $id = $this->readId();
        if ($id === self::CMD_FAIL) {
            $len = $this->readUint32LE();
            $msg = $this->socket->read($len);
            throw new AdbException("Push failed: {$msg}");
        }
        if ($id !== self::CMD_OKAY) {
            throw new ProtocolException("Expected OKAY after push, got: {$id}");
        }
        $this->socket->read(4); // trailing uint32

        return $transfer;
    }

    /**
     * Pull a file from the device.
     *
     * Mirrors `sync.pull(path)`.
     *
     * @param callable|null $onProgress `function(int $bytesTransferred): void`
     *
     * @return array{transfer: PullTransfer, data: string}
     *
     * @throws AdbException
     */
    public function pull(string $remotePath, ?callable $onProgress = null): array
    {
        $transfer = new PullTransfer();
        if ($onProgress !== null) {
            $transfer->onProgress($onProgress);
        }

        $this->sendCmd(self::CMD_RECV, $remotePath);

        $data = '';

        while (true) {
            if ($transfer->isCancelled()) {
                break;
            }

            $id = $this->readId();

            if ($id === self::CMD_DONE) {
                $this->socket->read(4);
                break;
            }
            if ($id === self::CMD_FAIL) {
                $len = $this->readUint32LE();
                $msg = $this->socket->read($len);
                throw new AdbException("Pull failed: {$msg}");
            }
            if ($id !== self::CMD_DATA) {
                throw new ProtocolException("Expected DATA, got: {$id}");
            }

            $len   = $this->readUint32LE();
            $chunk = $this->socket->read($len);
            $data .= $chunk;
            $transfer->addBytes($len);
        }

        return ['transfer' => $transfer, 'data' => $data];
    }

    /**
     * Generate a safe temporary file path on the device.
     *
     * Mirrors `sync.tempFile(path)`.
     */
    public function tempFile(string $path): string
    {
        return '/data/local/tmp/' . basename($path) . '.' . bin2hex(random_bytes(4));
    }

    /**
     * Close the SYNC session.
     *
     * Mirrors `sync.end()`.
     */
    public function end(): void
    {
        $this->sendCmd(self::CMD_QUIT, '');
        $this->socket->close();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function sendCmd(string $id, string $arg): void
    {
        $this->socket->write($id . pack('V', strlen($arg)) . $arg);
    }

    private function readId(): string
    {
        return $this->socket->read(4);
    }

    private function readUint32LE(): int
    {
        /** @var array{1: int} $u */
        $u = unpack('V', $this->socket->read(4));
        return $u[1];
    }
}
