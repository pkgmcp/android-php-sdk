<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit;

use AdbPhp\AdbClient;
use AdbPhp\DeviceClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdbClient::class)]
final class AdbClientTest extends TestCase
{
    #[Test]
    public function createReturnsAdbClientInstance(): void
    {
        $client = AdbClient::create();
        $this->assertInstanceOf(AdbClient::class, $client);
    }

    #[Test]
    public function getDeviceReturnsDeviceClient(): void
    {
        $adb    = AdbClient::create();
        $device = $adb->getDevice('emulator-5554');

        $this->assertInstanceOf(DeviceClient::class, $device);
        $this->assertSame('emulator-5554', $device->serial);
    }

    #[Test]
    public function typedConstantsHaveCorrectValues(): void
    {
        $this->assertSame('127.0.0.1', AdbClient::DEFAULT_HOST);
        $this->assertSame(5037, AdbClient::DEFAULT_PORT);
    }

    #[Test]
    public function parsePublicKeyExtractsFields(): void
    {
        $key    = base64_encode('fake-key-data');
        $result = AdbClient::parsePublicKey("{$key} fingerprint comment text");

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('fingerprint', $result);
        $this->assertArrayHasKey('comment', $result);
        $this->assertSame($key, $result['key']);
    }

    #[Test]
    public function parsePublicKeyHandlesMinimalInput(): void
    {
        $result = AdbClient::parsePublicKey('AAAA==');
        $this->assertSame('AAAA==', $result['key']);
        $this->assertSame('', $result['comment']);
    }

    #[Test]
    public function getDeviceDifferentSerialsReturnDistinctClients(): void
    {
        $adb = AdbClient::create();
        $d1  = $adb->getDevice('emulator-5554');
        $d2  = $adb->getDevice('emulator-5556');

        $this->assertNotSame($d1, $d2);
        $this->assertSame('emulator-5554', $d1->serial);
        $this->assertSame('emulator-5556', $d2->serial);
    }

    #[Test]
    public function createAcceptsCustomHostAndPort(): void
    {
        // Just checks it instantiates without error
        $client = AdbClient::create(host: '10.0.0.1', port: 5037, timeoutMs: 2000);
        $this->assertInstanceOf(AdbClient::class, $client);
    }
}
