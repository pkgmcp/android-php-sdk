<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit;

use FastbootPhp\CommandResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandResponse::class)]
final class CommandResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $response = new CommandResponse(text: 'pixel7', dataSize: null);

        $this->assertSame('pixel7', $response->text);
        $this->assertNull($response->dataSize);
    }

    #[Test]
    public function constructorSetsDataSize(): void
    {
        $response = new CommandResponse(text: '', dataSize: '00001000');

        $this->assertSame('', $response->text);
        $this->assertSame('00001000', $response->dataSize);
    }

    #[Test]
    public function isReadonlyClass(): void
    {
        $r = new \ReflectionClass(CommandResponse::class);
        $this->assertTrue($r->isReadOnly(), 'CommandResponse must be a readonly class (PHP 8.3)');
    }
}
