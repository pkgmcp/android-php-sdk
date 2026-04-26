<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit;

use FastbootPhp\UsbError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsbError::class)]
final class UsbErrorTest extends TestCase
{
    #[Test]
    public function isRuntimeException(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new UsbError('fail'));
    }

    #[Test]
    public function messageIsSet(): void
    {
        $e = new UsbError('device not found');
        $this->assertSame('device not found', $e->getMessage());
    }

    #[Test]
    public function acceptsCodeAndPrevious(): void
    {
        $prev = new \Exception('prev');
        $e    = new UsbError('msg', 42, $prev);
        $this->assertSame(42, $e->getCode());
        $this->assertSame($prev, $e->getPrevious());
    }
}
