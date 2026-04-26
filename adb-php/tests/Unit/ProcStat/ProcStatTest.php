<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\ProcStat;

use AdbPhp\ProcStat\ProcStat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcStat::class)]
final class ProcStatTest extends TestCase
{
    private const string SAMPLE_PROC_STAT = <<<'TXT'
cpu  392762 8730 109726 3474845 12354 0 8312 0 0 0
cpu0 105241 2134 28764 863420 3012 0 7001 0 0 0
cpu1 95871 2241 27412 870234 3021 0 841 0 0 0
cpu2 97124 2234 27601 868941 3134 0 312 0 0 0
cpu3 94526 2121 25949 872250 3187 0 158 0 0 0
intr 1234567 1 2 3
TXT;

    #[Test]
    public function parseExtractsAggregateCpu(): void
    {
        $stat = ProcStat::parse(self::SAMPLE_PROC_STAT);

        $this->assertSame(392762, $stat->cpu->user);
        $this->assertSame(8730,   $stat->cpu->nice);
        $this->assertSame(109726, $stat->cpu->system);
        $this->assertSame(3474845, $stat->cpu->idle);
    }

    #[Test]
    public function parseExtractsPerCoreStats(): void
    {
        $stat = ProcStat::parse(self::SAMPLE_PROC_STAT);

        $this->assertCount(4, $stat->cores);
        $this->assertArrayHasKey('cpu0', $stat->cores);
        $this->assertArrayHasKey('cpu3', $stat->cores);
        $this->assertSame(105241, $stat->cores['cpu0']->user);
    }

    #[Test]
    public function totalIncludesAllFields(): void
    {
        $stat  = ProcStat::parse(self::SAMPLE_PROC_STAT);
        $total = $stat->cpu->user + $stat->cpu->nice + $stat->cpu->system
               + $stat->cpu->idle + $stat->cpu->iowait + $stat->cpu->irq + $stat->cpu->softirq;

        $this->assertSame($total, $stat->cpu->total());
    }

    #[Test]
    public function activeExcludesIdleAndIowait(): void
    {
        $stat   = ProcStat::parse(self::SAMPLE_PROC_STAT);
        $active = $stat->cpu->total() - $stat->cpu->idle - $stat->cpu->iowait;
        $this->assertSame($active, $stat->cpu->active());
    }

    #[Test]
    public function parseThrowsOnInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProcStat::parse("intr 12345\nmem 1000");
    }

    #[Test]
    public function parseHandlesMinimalInput(): void
    {
        $stat = ProcStat::parse("cpu  100 0 50 800 0 0 0\n");
        $this->assertSame(100, $stat->cpu->user);
        $this->assertCount(0, $stat->cores);
    }
}
