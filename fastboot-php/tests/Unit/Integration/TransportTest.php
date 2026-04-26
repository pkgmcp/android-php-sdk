<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit\Integration;

use FastbootPhp\Transport\TcpTransport;
use FastbootPhp\Transport\LibUsbTransport;
use FastbootPhp\UsbError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TcpTransport::class)]
#[CoversClass(LibUsbTransport::class)]
final class TransportTest extends TestCase
{
    // -------------------------------------------------------------------------
    // TcpTransport
    // -------------------------------------------------------------------------

    #[Test]
    public function tcpTransportNotConnectedBeforeOpen(): void
    {
        $t = new TcpTransport('127.0.0.1', 1);
        $this->assertFalse($t->isConnected());
    }

    #[Test]
    public function tcpTransportOpenThrowsOnRefusedPort(): void
    {
        $t = new TcpTransport('127.0.0.1', 1, 200); // port 1 always refused
        $this->expectException(UsbError::class);
        $t->open();
    }

    #[Test]
    public function tcpTransportCloseIsIdempotent(): void
    {
        $t = new TcpTransport('127.0.0.1', 1, 200);
        $t->close(); // calling close before open should not throw
        $this->assertFalse($t->isConnected());
    }

    #[Test]
    public function tcpTransportTransferOutThrowsWhenNotOpen(): void
    {
        $t = new TcpTransport('127.0.0.1', 1);
        $this->expectException(UsbError::class);
        $t->transferOut('data');
    }

    #[Test]
    public function tcpTransportTransferInThrowsWhenNotOpen(): void
    {
        $t = new TcpTransport('127.0.0.1', 1);
        $this->expectException(UsbError::class);
        $t->transferIn(64);
    }

    // -------------------------------------------------------------------------
    // LibUsbTransport
    // -------------------------------------------------------------------------

    #[Test]
    public function libUsbTransportNotConnectedBeforeOpen(): void
    {
        $t = new LibUsbTransport('/nonexistent/device');
        $this->assertFalse($t->isConnected());
    }

    #[Test]
    public function libUsbTransportOpenThrowsForMissingDevice(): void
    {
        $t = new LibUsbTransport('/dev/nonexistent_usb_device_xyz');
        $this->expectException(UsbError::class);
        $t->open();
    }

    #[Test]
    public function libUsbTransportCloseIsIdempotent(): void
    {
        $t = new LibUsbTransport('/nonexistent');
        $t->close(); // no throw
        $this->assertFalse($t->isConnected());
    }

    #[Test]
    public function libUsbTransportTransferOutThrowsWhenNotOpen(): void
    {
        $t = new LibUsbTransport('/nonexistent');
        $this->expectException(UsbError::class);
        $t->transferOut('data');
    }

    #[Test]
    public function libUsbTransportTransferInThrowsWhenNotOpen(): void
    {
        $t = new LibUsbTransport('/nonexistent');
        $this->expectException(UsbError::class);
        $t->transferIn(64);
    }

    // -------------------------------------------------------------------------
    // TcpTransport via injected stream (offline test)
    // -------------------------------------------------------------------------

    #[Test]
    public function tcpTransportReadWriteViaInjectedStream(): void
    {
        $t   = new TcpTransport('127.0.0.1', 5555, 1000);
        $tmp = tmpfile();
        fwrite($tmp, 'OKAYresponse');
        rewind($tmp);

        // Inject socket via reflection
        $ref = new \ReflectionProperty(TcpTransport::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($t, $tmp);

        $this->assertTrue($t->isConnected());

        $data = $t->transferIn(4);
        $this->assertSame('OKAY', $data);
    }

    #[Test]
    public function libUsbTransportReadWriteViaInjectedStream(): void
    {
        $t   = new LibUsbTransport('/dev/fake', 1000);
        $tmp = tmpfile();
        fwrite($tmp, 'OKAY');
        rewind($tmp);

        $ref = new \ReflectionProperty(LibUsbTransport::class, 'handle');
        $ref->setAccessible(true);
        $ref->setValue($t, $tmp);

        $this->assertTrue($t->isConnected());

        $data = $t->transferIn(4);
        $this->assertSame('OKAY', $data);
    }
}
