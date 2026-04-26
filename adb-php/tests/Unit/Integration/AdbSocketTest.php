<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Integration;

use AdbPhp\Exceptions\ProtocolException;
use AdbPhp\Protocol\AdbSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdbSocket::class)]
final class AdbSocketTest extends TestCase
{
    /**
     * Build an AdbSocket with a pre-seeded memory stream as the underlying socket.
     */
    private function makeSocket(string $serverData): AdbSocket
    {
        $sock = new AdbSocket('127.0.0.1', 5037, 1000);
        $tmp  = tmpfile();
        fwrite($tmp, $serverData);
        rewind($tmp);

        $ref = new \ReflectionProperty(AdbSocket::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($sock, $tmp);

        return $sock;
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    #[Test]
    public function typedConstantsHaveCorrectValues(): void
    {
        $this->assertSame('OKAY', AdbSocket::STATUS_OKAY);
        $this->assertSame('FAIL', AdbSocket::STATUS_FAIL);
    }

    // -------------------------------------------------------------------------
    // read()
    // -------------------------------------------------------------------------

    #[Test]
    public function readExactlyNBytes(): void
    {
        $sock   = $this->makeSocket('ABCDEFGHIJ');
        $result = $sock->read(4);
        $this->assertSame('ABCD', $result);
    }

    #[Test]
    public function readLengthPrefixedDecodesCorrectly(): void
    {
        // 4-hex length "0005" + "hello"
        $sock   = $this->makeSocket('0005hello');
        $result = $sock->readLengthPrefixed();
        $this->assertSame('hello', $result);
    }

    #[Test]
    public function readLengthPrefixedZeroLength(): void
    {
        $sock   = $this->makeSocket('0000');
        $result = $sock->readLengthPrefixed();
        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // readStatus()
    // -------------------------------------------------------------------------

    #[Test]
    public function readStatusPassesOnOkay(): void
    {
        $sock = $this->makeSocket('OKAY');
        $sock->readStatus(); // should not throw
        $this->assertTrue(true);
    }

    #[Test]
    public function readStatusThrowsOnFail(): void
    {
        $msg  = 'device not found';
        $hex  = sprintf('%04X', strlen($msg));
        $sock = $this->makeSocket('FAIL' . $hex . $msg);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('device not found');
        $sock->readStatus();
    }

    #[Test]
    public function readStatusThrowsOnUnknownStatus(): void
    {
        $sock = $this->makeSocket('BLAH');

        $this->expectException(ProtocolException::class);
        $sock->readStatus();
    }

    // -------------------------------------------------------------------------
    // send() / write()
    // -------------------------------------------------------------------------

    #[Test]
    public function sendWritesLengthPrefixedPayload(): void
    {
        $sock   = $this->makeSocket('');
        $output = fopen('php://memory', 'r+b');

        // Inject write-capture stream
        $ref = new \ReflectionProperty(AdbSocket::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($sock, $output);

        $sock->send('host:version');

        rewind($output);
        $written = stream_get_contents($output);

        $expected = sprintf('%04X', strlen('host:version')) . 'host:version';
        $this->assertSame($expected, $written);
    }

    // -------------------------------------------------------------------------
    // isConnected()
    // -------------------------------------------------------------------------

    #[Test]
    public function isConnectedTrueWhenStreamOpen(): void
    {
        $sock = $this->makeSocket('');
        $this->assertTrue($sock->isConnected());
    }

    #[Test]
    public function isConnectedFalseAfterClose(): void
    {
        $sock = $this->makeSocket('');
        $sock->close();
        $this->assertFalse($sock->isConnected());
    }

    // -------------------------------------------------------------------------
    // readAll()
    // -------------------------------------------------------------------------

    #[Test]
    public function readAllReturnsEntireStream(): void
    {
        $data = "line1\nline2\nline3";
        $sock = $this->makeSocket($data);
        $this->assertSame($data, $sock->readAll());
    }
}
