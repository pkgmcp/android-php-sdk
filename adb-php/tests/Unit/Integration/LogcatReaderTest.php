<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Integration;

use AdbPhp\Logcat\LogcatEntry;
use AdbPhp\Logcat\LogcatReader;
use AdbPhp\Protocol\AdbSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogcatReader::class)]
final class LogcatReaderTest extends TestCase
{
    /**
     * Build a logger_entry v3 binary packet.
     *
     * Binary format:
     *   uint16 payload_length
     *   uint16 header_size     (20 bytes minimum)
     *   int32  pid
     *   int32  tid
     *   int32  sec
     *   int32  nsec
     *   <payload>
     *     uint8  priority
     *     char[] tag \0
     *     char[] message \0
     */
    private function makeLogPacket(
        int    $pid,
        int    $tid,
        int    $sec,
        int    $priority,
        string $tag,
        string $message,
    ): string {
        $payload = chr($priority) . $tag . "\x00" . $message . "\x00";
        $payloadLen  = strlen($payload);
        $headerSize  = 20; // 4 + 16 header bytes after the first uint16 pair

        return pack('vv', $payloadLen, $headerSize)   // payload_length, header_size
             . pack('VVVv', $pid, $tid, $sec, 0)      // pid, tid, sec, nsec (partial)
             . $payload;
    }

    /**
     * Inject a pre-built byte string into an AdbSocket via Reflection.
     */
    private function makeSocketWithData(string $data): AdbSocket
    {
        $sock = new AdbSocket('127.0.0.1', 5037, 1000);
        $tmp  = tmpfile();
        fwrite($tmp, $data);
        rewind($tmp);

        $ref = new \ReflectionProperty(AdbSocket::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($sock, $tmp);

        return $sock;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    #[Test]
    public function readParsesASingleLogEntry(): void
    {
        $packet = $this->makeLogPacket(
            pid:      1234,
            tid:      5678,
            sec:      1700000000,
            priority: LogcatEntry::PRIORITY_INFO,
            tag:      'MyTag',
            message:  'Hello from logcat',
        );

        $sock   = $this->makeSocketWithData($packet);
        $reader = new LogcatReader($sock);
        $entry  = $reader->read();

        $this->assertInstanceOf(LogcatEntry::class, $entry);
        $this->assertSame(1234, $entry->pid);
        $this->assertSame(5678, $entry->tid);
        $this->assertSame(1700000000, $entry->date);
        $this->assertSame(LogcatEntry::PRIORITY_INFO, $entry->priority);
        $this->assertSame('MyTag', $entry->tag);
        $this->assertSame('Hello from logcat', $entry->message);
    }

    #[Test]
    public function readAllParsesMultipleEntries(): void
    {
        $packet1 = $this->makeLogPacket(100, 100, 1000, LogcatEntry::PRIORITY_DEBUG, 'TAG1', 'msg1');
        $packet2 = $this->makeLogPacket(200, 200, 2000, LogcatEntry::PRIORITY_ERROR, 'TAG2', 'msg2');
        $packet3 = $this->makeLogPacket(300, 300, 3000, LogcatEntry::PRIORITY_WARN,  'TAG3', 'msg3');

        $sock   = $this->makeSocketWithData($packet1 . $packet2 . $packet3);
        $reader = new LogcatReader($sock);

        $entries = $reader->readAll();

        $this->assertCount(3, $entries);
        $this->assertSame('TAG1', $entries[0]->tag);
        $this->assertSame('msg1', $entries[0]->message);
        $this->assertSame(LogcatEntry::PRIORITY_DEBUG, $entries[0]->priority);

        $this->assertSame('TAG2', $entries[1]->tag);
        $this->assertSame(LogcatEntry::PRIORITY_ERROR, $entries[1]->priority);

        $this->assertSame('TAG3', $entries[2]->tag);
        $this->assertSame(LogcatEntry::PRIORITY_WARN, $entries[2]->priority);
    }

    #[Test]
    public function streamStopsWhenCallbackReturnsFalse(): void
    {
        $p1 = $this->makeLogPacket(1, 1, 1, LogcatEntry::PRIORITY_INFO, 'T', 'msg1');
        $p2 = $this->makeLogPacket(1, 1, 2, LogcatEntry::PRIORITY_INFO, 'T', 'msg2');
        $p3 = $this->makeLogPacket(1, 1, 3, LogcatEntry::PRIORITY_INFO, 'T', 'msg3');

        $sock   = $this->makeSocketWithData($p1 . $p2 . $p3);
        $reader = new LogcatReader($sock);

        $seen = [];
        $reader->stream(static function (LogcatEntry $e) use (&$seen): bool {
            $seen[] = $e->message;
            return $e->message !== 'msg2'; // stop after msg2
        });

        $this->assertSame(['msg1', 'msg2'], $seen);
    }

    #[Test]
    public function readReturnsNullOnEmptyStream(): void
    {
        $sock   = $this->makeSocketWithData('');
        $reader = new LogcatReader($sock);

        $this->assertNull($reader->read());
    }

    #[Test]
    public function endClosesSocket(): void
    {
        $sock   = $this->makeSocketWithData('');
        $reader = new LogcatReader($sock);
        $reader->end();

        $this->assertFalse($sock->isConnected());
    }

    #[Test]
    public function priorityLabelsAreCorrect(): void
    {
        $packet = $this->makeLogPacket(1, 1, 1, LogcatEntry::PRIORITY_WARN, 'TAG', 'warn msg');
        $sock   = $this->makeSocketWithData($packet);
        $reader = new LogcatReader($sock);
        $entry  = $reader->read();

        $this->assertSame('W', $entry->priorityLabel());
        $this->assertSame('W/TAG(1): warn msg', (string) $entry);
    }
}
