<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Transfers;

use AdbPhp\Transfers\PushTransfer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushTransfer::class)]
final class PushTransferTest extends TestCase
{
    #[Test]
    public function initialStateIsZeroAndNotCancelled(): void
    {
        $t = new PushTransfer();
        $this->assertSame(0, $t->getBytesTransferred());
        $this->assertFalse($t->isCancelled());
    }

    #[Test]
    public function addBytesAccumulates(): void
    {
        $t = new PushTransfer();
        $t->addBytes(512);
        $t->addBytes(512);
        $this->assertSame(1024, $t->getBytesTransferred());
    }

    #[Test]
    public function onProgressCallbackIsCalled(): void
    {
        $t      = new PushTransfer();
        $called = [];
        $t->onProgress(static function (int $b) use (&$called): void { $called[] = $b; });

        $t->addBytes(100);
        $t->addBytes(200);

        $this->assertSame([100, 300], $called);
    }

    #[Test]
    public function cancelSetsCancelledFlag(): void
    {
        $t = new PushTransfer();
        $t->cancel();
        $this->assertTrue($t->isCancelled());
    }

    #[Test]
    public function onProgressReturnsFluentSelf(): void
    {
        $t = new PushTransfer();
        $this->assertSame($t, $t->onProgress(static fn() => null));
    }
}
