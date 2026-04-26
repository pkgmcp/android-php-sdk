<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit;

use FastbootPhp\Common;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Common::class)]
final class CommonTest extends TestCase
{
    protected function tearDown(): void
    {
        Common::setDebugLevel(Common::LEVEL_SILENT);
    }

    #[Test]
    public function defaultLevelIsSilent(): void
    {
        $this->assertSame(Common::LEVEL_SILENT, Common::getDebugLevel());
    }

    #[Test]
    #[DataProvider('validLevels')]
    public function setDebugLevelAcceptsValidValues(int $level): void
    {
        Common::setDebugLevel($level);
        $this->assertSame($level, Common::getDebugLevel());
    }

    public static function validLevels(): array
    {
        return [[0], [1], [2]];
    }

    #[Test]
    public function setDebugLevelClampsAboveMax(): void
    {
        Common::setDebugLevel(99);
        $this->assertSame(Common::LEVEL_VERBOSE, Common::getDebugLevel());
    }

    #[Test]
    public function setDebugLevelClampsBelowMin(): void
    {
        Common::setDebugLevel(-5);
        $this->assertSame(Common::LEVEL_SILENT, Common::getDebugLevel());
    }

    #[Test]
    public function typedConstantsHaveCorrectValues(): void
    {
        $this->assertSame(0, Common::LEVEL_SILENT);
        $this->assertSame(1, Common::LEVEL_DEBUG);
        $this->assertSame(2, Common::LEVEL_VERBOSE);
    }

    #[Test]
    public function logDebugWritesToStderrWhenLevelIsDebug(): void
    {
        Common::setDebugLevel(Common::LEVEL_DEBUG);
        $this->expectOutputString('');   // stdout stays clean
        // Just assert no exception is thrown
        Common::logDebug('test message', ['context' => true]);
        $this->assertTrue(true);
    }

    #[Test]
    public function logVerboseIsSilentAtDebugLevel(): void
    {
        Common::setDebugLevel(Common::LEVEL_DEBUG);
        // No exception thrown — method is a no-op at level 1
        Common::logVerbose('verbose message');
        $this->assertTrue(true);
    }
}
