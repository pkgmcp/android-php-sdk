<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit\Transport;

use FastbootPhp\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockTransport::class)]
final class MockTransportTest extends TestCase
{
    #[Test]
    public function notConnectedByDefault(): void
    {
        $mock = new MockTransport();
        $this->assertFalse($mock->isConnected());
    }

    #[Test]
    public function openSetsConnected(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $this->assertTrue($mock->isConnected());
    }

    #[Test]
    public function closeClearsConnected(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $mock->close();
        $this->assertFalse($mock->isConnected());
    }

    #[Test]
    public function transferOutAccumulatesSentData(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $mock->transferOut('hello');
        $mock->transferOut('world');

        $this->assertSame(['hello', 'world'], $mock->getSentData());
    }

    #[Test]
    public function transferInDequeuesResponses(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $mock->queueResponse('OKAYfoo');
        $mock->queueResponse('OKAYbar');

        $this->assertSame('OKAYfoo', $mock->transferIn(64));
        $this->assertSame('OKAYbar', $mock->transferIn(64));
    }

    #[Test]
    public function transferInRespectsMaxLength(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $mock->queueResponse('OKAYlongresponse');

        $this->assertSame('OKAY', $mock->transferIn(4));
    }

    #[Test]
    public function transferInThrowsWhenQueueEmpty(): void
    {
        $mock = new MockTransport();
        $mock->open();

        $this->expectException(\UnderflowException::class);
        $mock->transferIn(64);
    }

    #[Test]
    public function clearStateResetsEverything(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $mock->queueResponse('OKAYtest');
        $mock->transferOut('cmd');
        $mock->clearState();

        $this->assertFalse($mock->isConnected());
        $this->assertSame([], $mock->getSentData());
    }

    #[Test]
    public function resetIsNoOp(): void
    {
        $mock = new MockTransport();
        $mock->open();
        $mock->reset(); // should not throw
        $this->assertTrue($mock->isConnected());
    }
}
