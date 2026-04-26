<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Models;

use AdbPhp\Models\Device;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Device::class)]
final class DeviceTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $d = new Device(id: 'emulator-5554', type: 'device');
        $this->assertSame('emulator-5554', $d->id);
        $this->assertSame('device', $d->type);
    }

    #[Test]
    public function toStringFormatIsCorrect(): void
    {
        $d = new Device('emulator-5554', 'device');
        $this->assertSame("emulator-5554\tdevice", (string) $d);
    }

    #[Test]
    public function isReadonlyClass(): void
    {
        $r = new \ReflectionClass(Device::class);
        $this->assertTrue($r->isReadOnly());
    }
}
