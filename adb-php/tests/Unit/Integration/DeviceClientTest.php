<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Integration;

use AdbPhp\AdbClient;
use AdbPhp\DeviceClient;
use AdbPhp\Exceptions\AdbException;
use AdbPhp\Protocol\AdbSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DeviceClient integration tests.
 *
 * Uses a FakeAdbClient that returns pre-programmed AdbSocket responses,
 * eliminating the need for a real ADB server.
 */
#[CoversClass(DeviceClient::class)]
final class DeviceClientTest extends TestCase
{
    /**
     * Build an AdbSocket backed by a tmpfile with pre-seeded server bytes.
     */
    private function makeSocket(string ...$responses): AdbSocket
    {
        $combined = implode('', $responses);
        $sock     = new AdbSocket('127.0.0.1', 5037, 1000);
        $tmp      = tmpfile();
        fwrite($tmp, $combined);
        rewind($tmp);

        $ref = new \ReflectionProperty(AdbSocket::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($sock, $tmp);

        return $sock;
    }

    /**
     * Build an AdbClient whose openSocket() always returns the given socket.
     */
    private function makeClient(AdbSocket ...$sockets): AdbClient
    {
        $queue = $sockets;

        $client = $this->getMockBuilder(AdbClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['openSocket'])
            ->getMock();

        $client->method('openSocket')
            ->willReturnCallback(static function () use (&$queue): AdbSocket {
                return array_shift($queue) ?? throw new \UnderflowException('No more sockets queued');
            });

        return $client;
    }

    /** Build an ADB "OKAY" + length-prefixed body response. */
    private function okayBody(string $body = ''): string
    {
        return 'OKAY' . sprintf('%04X', strlen($body)) . $body;
    }

    /** Build an ADB transport-switch OKAY (no body). */
    private function transportOkay(): string
    {
        return 'OKAY';
    }

    // -------------------------------------------------------------------------
    // Typed constants
    // -------------------------------------------------------------------------

    #[Test]
    public function typedConstantsExist(): void
    {
        $this->assertSame(60,   DeviceClient::WAIT_DEVICE_TIMEOUT);
        $this->assertSame(120,  DeviceClient::WAIT_BOOT_TIMEOUT);
        $this->assertSame(1080, DeviceClient::DEFAULT_MONKEY_PORT);
    }

    // -------------------------------------------------------------------------
    // shell()
    // -------------------------------------------------------------------------

    #[Test]
    public function shellExecutesCommandAndReturnsOutput(): void
    {
        // socket 1: transport switch OKAY  +  shell command OKAY  +  stream output
        $sock = $this->makeSocket(
            $this->transportOkay(),  // host:transport:emulator-5554
            'OKAY',                  // shell:uname -a
            "Linux localhost 5.10\n", // streamed output
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $output = $device->shell('uname -a');
        $this->assertStringContainsString('Linux', $output);
    }

    // -------------------------------------------------------------------------
    // getSerialNo()
    // -------------------------------------------------------------------------

    #[Test]
    public function getSerialNoReturnsSerial(): void
    {
        $sock = $this->makeSocket(
            $this->okayBody('emulator-5554'),
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $this->assertSame('emulator-5554', $device->getSerialNo());
    }

    // -------------------------------------------------------------------------
    // getState()
    // -------------------------------------------------------------------------

    #[Test]
    public function getStateReturnsDeviceState(): void
    {
        $sock = $this->makeSocket(
            $this->okayBody('device'),
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $this->assertSame('device', $device->getState());
    }

    // -------------------------------------------------------------------------
    // isInstalled()
    // -------------------------------------------------------------------------

    #[Test]
    public function isInstalledReturnsTrueWhenPackagePresent(): void
    {
        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            "package:com.example.app\n",
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $this->assertTrue($device->isInstalled('com.example.app'));
    }

    #[Test]
    public function isInstalledReturnsFalseWhenPackageAbsent(): void
    {
        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            "\n",
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $this->assertFalse($device->isInstalled('com.nothere.app'));
    }

    // -------------------------------------------------------------------------
    // getProperties()
    // -------------------------------------------------------------------------

    #[Test]
    public function getPropertiesParsesGetpropOutput(): void
    {
        $output = "[ro.product.model]: [Pixel 7]\n[ro.build.version.release]: [13]\n";

        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            $output,
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');
        $props  = $device->getProperties();

        $this->assertSame('Pixel 7', $props['ro.product.model']);
        $this->assertSame('13', $props['ro.build.version.release']);
    }

    // -------------------------------------------------------------------------
    // getPackages()
    // -------------------------------------------------------------------------

    #[Test]
    public function getPackagesParsesListOutput(): void
    {
        $output = "package:com.android.settings\npackage:com.google.android.gms\n";

        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            $output,
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');
        $pkgs   = $device->getPackages();

        $this->assertContains('com.android.settings', $pkgs);
        $this->assertContains('com.google.android.gms', $pkgs);
        $this->assertCount(2, $pkgs);
    }

    // -------------------------------------------------------------------------
    // getDHCPIpAddress()
    // -------------------------------------------------------------------------

    #[Test]
    public function getDHCPIpAddressReturnsIp(): void
    {
        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            "192.168.1.100\n",
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $this->assertSame('192.168.1.100', $device->getDHCPIpAddress());
    }

    #[Test]
    public function getDHCPIpAddressReturnsNullWhenEmpty(): void
    {
        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            "\n",
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');

        $this->assertNull($device->getDHCPIpAddress());
    }

    // -------------------------------------------------------------------------
    // getFeatures()
    // -------------------------------------------------------------------------

    #[Test]
    public function getFeaturesParsesFeatureList(): void
    {
        $output = "feature:android.hardware.nfc\nfeature:android.hardware.camera=1\n";

        $sock = $this->makeSocket(
            $this->transportOkay(),
            'OKAY',
            $output,
        );

        $client = $this->makeClient($sock);
        $device = new DeviceClient($client, 'emulator-5554');
        $feats  = $device->getFeatures();

        $this->assertArrayHasKey('android.hardware.nfc', $feats);
        $this->assertArrayHasKey('android.hardware.camera', $feats);
    }

    // -------------------------------------------------------------------------
    // serial property
    // -------------------------------------------------------------------------

    #[Test]
    public function serialPropertyIsPublicReadonly(): void
    {
        $adb    = AdbClient::create();
        $device = new DeviceClient($adb, 'test-serial-123');
        $this->assertSame('test-serial-123', $device->serial);
    }
}
