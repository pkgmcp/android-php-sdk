<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Models;

use AdbPhp\Models\CpuStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuStats::class)]
final class CpuStatsTest extends TestCase
{
    private function makeStats(
        int $user = 100, int $nice = 10, int $system = 50,
        int $idle = 800, int $iowait = 20, int $irq = 5, int $softirq = 3
    ): CpuStats {
        return new CpuStats($user, $nice, $system, $idle, $iowait, $irq, $softirq);
    }

    #[Test]
    public function totalSumsAllFields(): void
    {
        $s = $this->makeStats(100, 10, 50, 800, 20, 5, 3);
        $this->assertSame(988, $s->total());
    }

    #[Test]
    public function activeExcludesIdleAndIowait(): void
    {
        $s = $this->makeStats(100, 10, 50, 800, 20, 5, 3);
        $this->assertSame(988 - 800 - 20, $s->active());
    }

    #[Test]
    public function activeIsZeroWhenAllIdle(): void
    {
        $s = new CpuStats(0, 0, 0, 1000, 0, 0, 0);
        $this->assertSame(0, $s->active());
    }

    #[Test]
    public function isReadonlyClass(): void
    {
        $r = new \ReflectionClass(CpuStats::class);
        $this->assertTrue($r->isReadOnly());
    }
}
