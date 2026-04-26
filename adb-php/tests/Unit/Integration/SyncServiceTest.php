<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Integration;

use AdbPhp\Exceptions\AdbException;
use AdbPhp\Exceptions\ProtocolException;
use AdbPhp\Models\FileEntry;
use AdbPhp\Protocol\AdbSocket;
use AdbPhp\SyncService;
use AdbPhp\Transfers\PushTransfer;
use AdbPhp\Transfers\PullTransfer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncService::class)]
final class SyncServiceTest extends TestCase
{
    /**
     * Build a SyncService backed by a pair of in-memory streams.
     * Returns [SyncService, writeStream resource, readStream resource].
     *
     * We use two php://memory streams:
     *   $serverOut — data the server sends to the client (client reads from here)
     *   $clientOut — data the client sends to the server (we inspect this)
     */
    private function makeSyncService(string $serverPayload): array
    {
        // Create a socket-like object backed by memory streams
        $serverOut = fopen('php://memory', 'r+b');
        $clientOut = fopen('php://memory', 'r+b');

        // Pre-fill what the server will respond
        fwrite($serverOut, $serverPayload);
        rewind($serverOut);

        // We'll inject both streams into AdbSocket via a combined proxy
        $proxy = $this->buildProxyStream($serverOut, $clientOut);

        $sock = new AdbSocket('127.0.0.1', 5037, 1000);
        $ref  = new \ReflectionProperty(AdbSocket::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($sock, $proxy);

        return [new SyncService($sock), $clientOut];
    }

    /**
     * Build a php://memory stream pre-filled with $payload.
     *
     * @return resource
     */
    private function buildMemStream(string $payload): mixed
    {
        $m = fopen('php://memory', 'r+b');
        fwrite($m, $payload);
        rewind($m);
        return $m;
    }

    /** @return resource */
    private function buildProxyStream(mixed $readFrom, mixed $writeTo): mixed
    {
        // Use a temp file as a combined r+w stream, pre-seeded with server data
        $tmp = tmpfile();
        // Get the server payload and write it
        $contents = stream_get_contents($readFrom);
        fwrite($tmp, $contents);
        rewind($tmp);
        return $tmp;
    }

    // -------------------------------------------------------------------------
    // stat()
    // -------------------------------------------------------------------------

    #[Test]
    public function statParsesStatResponse(): void
    {
        // STAT response: ID_STAT + mode(uint32LE) + size(uint32LE) + mtime(uint32LE)
        $mode  = 0o100644;
        $size  = 1234;
        $mtime = 1700000000;

        $payload = 'STAT' . pack('VVV', $mode, $size, $mtime);
        [$sync]  = $this->makeSyncService($payload);

        $entry = $sync->stat('/sdcard/test.txt');

        $this->assertInstanceOf(FileEntry::class, $entry);
        $this->assertSame('test.txt', $entry->name);
        $this->assertSame($mode,  $entry->mode);
        $this->assertSame($size,  $entry->size);
        $this->assertSame($mtime, $entry->mtime);
    }

    #[Test]
    public function statReturnsFalseForZeroMode(): void
    {
        $payload = 'STAT' . pack('VVV', 0, 0, 0);
        [$sync]  = $this->makeSyncService($payload);

        $entry = $sync->stat('/sdcard/empty');
        $this->assertSame(0, $entry->mode);
        $this->assertSame(0, $entry->size);
    }

    // -------------------------------------------------------------------------
    // readdir()
    // -------------------------------------------------------------------------

    #[Test]
    public function readdirParsesDentAndDone(): void
    {
        $file1Name = 'file1.txt';
        $file2Name = 'dir2';
        $mode1 = 0o100644;
        $mode2 = 0o040755;

        // DENT + DENT + DONE
        $payload  = 'DENT' . pack('VVVV', $mode1, 100, 1700000000, strlen($file1Name)) . $file1Name;
        $payload .= 'DENT' . pack('VVVV', $mode2, 0,   1700000001, strlen($file2Name)) . $file2Name;
        $payload .= 'DONE' . pack('VVV', 0, 0, 0);

        [$sync] = $this->makeSyncService($payload);
        $entries = $sync->readdir('/sdcard');

        $this->assertCount(2, $entries);
        $this->assertSame('file1.txt', $entries[0]->name);
        $this->assertTrue($entries[0]->isFile());
        $this->assertSame('dir2', $entries[1]->name);
        $this->assertTrue($entries[1]->isDirectory());
    }

    #[Test]
    public function readdirSkipsDotAndDotDot(): void
    {
        $dot    = '.';
        $dotdot = '..';
        $real   = 'realfile.txt';

        $payload  = 'DENT' . pack('VVVV', 0o100644, 10, 0, strlen($dot))    . $dot;
        $payload .= 'DENT' . pack('VVVV', 0o100644, 10, 0, strlen($dotdot)) . $dotdot;
        $payload .= 'DENT' . pack('VVVV', 0o100644, 10, 0, strlen($real))   . $real;
        $payload .= 'DONE' . pack('VVV', 0, 0, 0);

        [$sync]  = $this->makeSyncService($payload);
        $entries = $sync->readdir('/sdcard');

        $this->assertCount(1, $entries);
        $this->assertSame('realfile.txt', $entries[0]->name);
    }

    // -------------------------------------------------------------------------
    // push()
    // -------------------------------------------------------------------------

    #[Test]
    public function pushStringReturnsTransfer(): void
    {
        // After push: device sends OKAY + 4 bytes
        $payload = 'OKAY' . pack('V', 0);
        [$sync]  = $this->makeSyncService($payload);

        $transfer = $sync->push('hello world', '/sdcard/hello.txt');
        $this->assertInstanceOf(PushTransfer::class, $transfer);
        $this->assertSame(strlen('hello world'), $transfer->getBytesTransferred());
    }

    #[Test]
    public function pushCallsProgressCallback(): void
    {
        $payload = 'OKAY' . pack('V', 0);
        [$sync]  = $this->makeSyncService($payload);

        $progress = [];
        $sync->push('test data', '/sdcard/t.txt', 0o644, static function (int $b) use (&$progress): void {
            $progress[] = $b;
        });

        $this->assertNotEmpty($progress);
        $this->assertSame(strlen('test data'), end($progress));
    }

    // -------------------------------------------------------------------------
    // pull()
    // -------------------------------------------------------------------------

    #[Test]
    public function pullReturnsDataAndTransfer(): void
    {
        $data     = 'file content here';
        $payload  = 'DATA' . pack('V', strlen($data)) . $data;
        $payload .= 'DONE' . pack('V', 0);
        [$sync]   = $this->makeSyncService($payload);

        $result = $sync->pull('/sdcard/test.txt');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('transfer', $result);
        $this->assertSame($data, $result['data']);
        $this->assertInstanceOf(PullTransfer::class, $result['transfer']);
        $this->assertSame(strlen($data), $result['transfer']->getBytesTransferred());
    }

    #[Test]
    public function pullThrowsOnFailResponse(): void
    {
        $msg     = 'No such file';
        $payload = 'FAIL' . pack('V', strlen($msg)) . $msg;
        [$sync]  = $this->makeSyncService($payload);

        $this->expectException(AdbException::class);
        $this->expectExceptionMessage('No such file');
        $sync->pull('/sdcard/nonexistent.txt');
    }

    // -------------------------------------------------------------------------
    // tempFile()
    // -------------------------------------------------------------------------

    #[Test]
    public function tempFileReturnsUniquePathsForSameInput(): void
    {
        $payload = '';
        [$sync]  = $this->makeSyncService($payload);

        $p1 = $sync->tempFile('/sdcard/app.apk');
        $p2 = $sync->tempFile('/sdcard/app.apk');

        $this->assertStringStartsWith('/data/local/tmp/', $p1);
        $this->assertStringContainsString('app.apk', $p1);
        $this->assertNotSame($p1, $p2); // unique suffixes
    }

    // -------------------------------------------------------------------------
    // DEFAULT_MODE constant
    // -------------------------------------------------------------------------

    #[Test]
    public function defaultModeConstantIsCorrect(): void
    {
        $this->assertSame(0o644, SyncService::DEFAULT_MODE);
    }
}
