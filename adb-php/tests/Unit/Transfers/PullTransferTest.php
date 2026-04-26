<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Transfers;

use AdbPhp\Transfers\PullTransfer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PullTransfer::class)]
final class PullTransferTest extends TestCase
{
    #[Test]
    public function initialStateIsZeroNotCancelled(): void
    {
        $t = new PullTransfer();
        $this->assertSame(0, $t->getBytesTransferred());
        $this->assertFalse($t->isCancelled());
    }

    #[Test]
    public function addBytesAccumulates(): void
    {
        $t = new PullTransfer();
        $t->addBytes(1024);
        $t->addBytes(2048);
        $this->assertSame(3072, $t->getBytesTransferred());
    }

    #[Test]
    public function cancelFlagWorks(): void
    {
        $t = new PullTransfer();
        $this->assertFalse($t->isCancelled());
        $t->cancel();
        $this->assertTrue($t->isCancelled());
    }

    #[Test]
    public function progressCallbackReceivesRunningTotal(): void
    {
        $t      = new PullTransfer();
        $values = [];
        $t->onProgress(static function (int $b) use (&$values): void { $values[] = $b; });
        $t->addBytes(500);
        $t->addBytes(500);
        $this->assertSame([500, 1000], $values);
    }
}
