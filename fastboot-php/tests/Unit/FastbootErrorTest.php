<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit;

use FastbootPhp\FastbootError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastbootError::class)]
final class FastbootErrorTest extends TestCase
{
    #[Test]
    public function constructorSetsStatusAndMessage(): void
    {
        $e = new FastbootError(status: 'FAIL', bootloaderMessage: 'partition not found');

        $this->assertSame('FAIL', $e->status);
        $this->assertSame('partition not found', $e->bootloaderMessage);
    }

    #[Test]
    public function messageStringIncludesStatusAndBody(): void
    {
        $e = new FastbootError(status: 'FAIL', bootloaderMessage: 'invalid partition');

        $this->assertStringContainsString('FAIL', $e->getMessage());
        $this->assertStringContainsString('invalid partition', $e->getMessage());
    }

    #[Test]
    public function isRuntimeException(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new FastbootError('FAIL', 'oops'));
    }

    #[Test]
    public function acceptsPreviousThrowable(): void
    {
        $prev = new \RuntimeException('original');
        $e    = new FastbootError('FAIL', 'wrapped', $prev);

        $this->assertSame($prev, $e->getPrevious());
    }
}
