<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Logcat;

use AdbPhp\Logcat\LogcatEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogcatEntry::class)]
final class LogcatEntryTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $e = new LogcatEntry(
            date: 1700000000, pid: 1234, tid: 5678,
            priority: LogcatEntry::PRIORITY_INFO,
            tag: 'MyTag', message: 'Hello log',
        );

        $this->assertSame(1700000000, $e->date);
        $this->assertSame(1234, $e->pid);
        $this->assertSame(5678, $e->tid);
        $this->assertSame(LogcatEntry::PRIORITY_INFO, $e->priority);
        $this->assertSame('MyTag', $e->tag);
        $this->assertSame('Hello log', $e->message);
    }

    #[Test]
    #[DataProvider('priorityLabels')]
    public function priorityLabelIsCorrect(int $priority, string $expected): void
    {
        $e = new LogcatEntry(0, 0, 0, $priority, '', '');
        $this->assertSame($expected, $e->priorityLabel());
    }

    public static function priorityLabels(): array
    {
        return [
            [LogcatEntry::PRIORITY_VERBOSE, 'V'],
            [LogcatEntry::PRIORITY_DEBUG,   'D'],
            [LogcatEntry::PRIORITY_INFO,    'I'],
            [LogcatEntry::PRIORITY_WARN,    'W'],
            [LogcatEntry::PRIORITY_ERROR,   'E'],
            [LogcatEntry::PRIORITY_FATAL,   'F'],
            [LogcatEntry::PRIORITY_SILENT,  'S'],
            [99,                            '?'],
        ];
    }

    #[Test]
    public function toStringFormatIsCorrect(): void
    {
        $e = new LogcatEntry(0, 123, 0, LogcatEntry::PRIORITY_DEBUG, 'TAG', 'msg');
        $this->assertSame('D/TAG(123): msg', (string) $e);
    }

    #[Test]
    public function typedConstantsAreInts(): void
    {
        $this->assertSame(2, LogcatEntry::PRIORITY_VERBOSE);
        $this->assertSame(3, LogcatEntry::PRIORITY_DEBUG);
        $this->assertSame(4, LogcatEntry::PRIORITY_INFO);
        $this->assertSame(5, LogcatEntry::PRIORITY_WARN);
        $this->assertSame(6, LogcatEntry::PRIORITY_ERROR);
        $this->assertSame(7, LogcatEntry::PRIORITY_FATAL);
        $this->assertSame(8, LogcatEntry::PRIORITY_SILENT);
    }

    #[Test]
    public function isReadonlyClass(): void
    {
        $r = new \ReflectionClass(LogcatEntry::class);
        $this->assertTrue($r->isReadOnly());
    }
}
