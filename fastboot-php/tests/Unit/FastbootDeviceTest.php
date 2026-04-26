<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit;

use FastbootPhp\FastbootDevice;
use FastbootPhp\FastbootError;
use FastbootPhp\Transport\MockTransport;
use FastbootPhp\UsbError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastbootDevice::class)]
final class FastbootDeviceTest extends TestCase
{
    private MockTransport $mock;
    private FastbootDevice $device;

    protected function setUp(): void
    {
        $this->mock   = new MockTransport();
        $this->device = new FastbootDevice($this->mock);
    }

    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------

    #[Test]
    public function isConnectedFalseBeforeConnect(): void
    {
        $this->assertFalse($this->device->isConnected());
    }

    #[Test]
    public function isConnectedTrueAfterConnect(): void
    {
        $this->device->connect();
        $this->assertTrue($this->device->isConnected());
    }

    #[Test]
    public function isConnectedFalseAfterDisconnect(): void
    {
        $this->device->connect();
        $this->device->disconnect();
        $this->assertFalse($this->device->isConnected());
    }

    #[Test]
    public function connectWithoutTransportThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        (new FastbootDevice())->connect();
    }

    #[Test]
    public function setTransportReplacesMock(): void
    {
        $device = new FastbootDevice();
        $device->setTransport($this->mock);
        $device->connect();
        $this->assertTrue($device->isConnected());
    }

    // -------------------------------------------------------------------------
    // runCommand
    // -------------------------------------------------------------------------

    #[Test]
    public function runCommandSendsPayloadAndReturnsOkay(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAYdone');

        $response = $this->device->runCommand('reboot');

        $this->assertSame('done', $response->text);
        $this->assertNull($response->dataSize);
        $this->assertContains('reboot', $this->mock->getSentData());
    }

    #[Test]
    public function runCommandAggregatesInfoLines(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('INFOline1');
        $this->mock->queueResponse('OKAYline2');

        $response = $this->device->runCommand('getvar:all');
        $this->assertStringContainsString('line1', $response->text);
        $this->assertStringContainsString('line2', $response->text);
    }

    #[Test]
    public function runCommandThrowsFastbootErrorOnFail(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('FAILcommand not found');

        $this->expectException(FastbootError::class);
        $this->expectExceptionMessage('command not found');
        $this->device->runCommand('bogus');
    }

    #[Test]
    public function runCommandThrowsFastbootErrorOnUnknownStatus(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('????garbage');

        $this->expectException(FastbootError::class);
        $this->device->runCommand('test');
    }

    #[Test]
    public function runCommandThrowsUsbErrorWhenNotConnected(): void
    {
        $this->expectException(UsbError::class);
        $this->device->runCommand('test');
    }

    // -------------------------------------------------------------------------
    // getVariable
    // -------------------------------------------------------------------------

    #[Test]
    public function getVariableReturnsValue(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAYpixel7');

        $this->assertSame('pixel7', $this->device->getVariable('product'));
    }

    #[Test]
    public function getVariableReturnsNullForEmptyValue(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');

        $this->assertNull($this->device->getVariable('unknown'));
    }

    #[Test]
    public function getVariableReturnsNullOnFail(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('FAILno such variable');

        $this->assertNull($this->device->getVariable('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // getMaxDownloadSize
    // -------------------------------------------------------------------------

    #[Test]
    public function getMaxDownloadSizeReturnsDefaultWhenNotAdvertised(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');  // empty response

        $size = $this->device->getMaxDownloadSize();
        $this->assertSame(FastbootDevice::DEFAULT_DOWNLOAD_SIZE, $size);
    }

    #[Test]
    public function getMaxDownloadSizeParsesHexValue(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY20000000');  // 512 MiB

        $size = $this->device->getMaxDownloadSize();
        $this->assertSame(0x20000000, $size);
    }

    #[Test]
    public function getMaxDownloadSizeCapsAtMaxDownloadSize(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY80000000');  // 2 GiB — should be capped

        $size = $this->device->getMaxDownloadSize();
        $this->assertSame(FastbootDevice::MAX_DOWNLOAD_SIZE, $size);
    }

    // -------------------------------------------------------------------------
    // Typed constants
    // -------------------------------------------------------------------------

    #[Test]
    public function typedConstantsExist(): void
    {
        $this->assertSame(16384, FastbootDevice::BULK_TRANSFER_SIZE);
        $this->assertSame(512 * 1024 * 1024, FastbootDevice::DEFAULT_DOWNLOAD_SIZE);
        $this->assertSame(1024 * 1024 * 1024, FastbootDevice::MAX_DOWNLOAD_SIZE);
        $this->assertSame(10_000, FastbootDevice::GETVAR_TIMEOUT_MS);
    }

    // -------------------------------------------------------------------------
    // upload (basic)
    // -------------------------------------------------------------------------

    #[Test]
    public function uploadSendsDownloadCommandThenPayload(): void
    {
        $this->device->connect();

        $data    = str_repeat('X', 16);
        $hexSize = str_pad(dechex(strlen($data)), 8, '0', STR_PAD_LEFT);

        $this->mock->queueResponse("DATA{$hexSize}");  // download: response
        $this->mock->queueResponse('OKAY');             // after payload
        $this->mock->queueResponse('OKAY');             // status read

        $this->device->upload('boot', $data);

        $sent = $this->mock->getSentData();
        $this->assertSame("download:{$hexSize}", $sent[0]);
    }

    // -------------------------------------------------------------------------
    // Reboot helpers
    // -------------------------------------------------------------------------

    #[Test]
    public function rebootSendsCorrectCommand(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');

        $this->device->reboot();
        $this->assertContains('reboot', $this->mock->getSentData());
    }

    #[Test]
    public function rebootBootloaderSendsCorrectCommand(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');

        $this->device->rebootBootloader();
        $this->assertContains('reboot-bootloader', $this->mock->getSentData());
    }

    // -------------------------------------------------------------------------
    // Erase / Lock / Unlock
    // -------------------------------------------------------------------------

    #[Test]
    public function eraseSendsEraseCommand(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');

        $this->device->erase('userdata');
        $this->assertContains('erase:userdata', $this->mock->getSentData());
    }

    #[Test]
    public function lockSendsFlashingLock(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');

        $this->device->lock();
        $this->assertContains('flashing lock', $this->mock->getSentData());
    }

    #[Test]
    public function unlockSendsFlashingUnlock(): void
    {
        $this->device->connect();
        $this->mock->queueResponse('OKAY');

        $this->device->unlock();
        $this->assertContains('flashing unlock', $this->mock->getSentData());
    }
}
