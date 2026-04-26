<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit;

use AdbPhp\Exceptions\AdbException;
use AdbPhp\Exceptions\ConnectionException;
use AdbPhp\Exceptions\DeviceNotFoundException;
use AdbPhp\Exceptions\ProtocolException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdbException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(DeviceNotFoundException::class)]
#[CoversClass(ProtocolException::class)]
final class ExceptionsTest extends TestCase
{
    #[Test]
    public function adbExceptionIsRuntimeException(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new AdbException('fail'));
    }

    #[Test]
    public function connectionExceptionIsAdbException(): void
    {
        $this->assertInstanceOf(AdbException::class, new ConnectionException('fail'));
    }

    #[Test]
    public function protocolExceptionIsAdbException(): void
    {
        $this->assertInstanceOf(AdbException::class, new ProtocolException('fail'));
    }

    #[Test]
    public function deviceNotFoundExceptionIncludesSerial(): void
    {
        $e = new DeviceNotFoundException('emulator-5554');
        $this->assertStringContainsString('emulator-5554', $e->getMessage());
    }

    #[Test]
    public function deviceNotFoundExceptionIsAdbException(): void
    {
        $this->assertInstanceOf(AdbException::class, new DeviceNotFoundException('device-1'));
    }
}
