<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * CPU statistics for a single core, parsed from /proc/stat.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class CpuStats
{
    public function __construct(
        public int $user,
        public int $nice,
        public int $system,
        public int $idle,
        public int $iowait,
        public int $irq,
        public int $softirq,
    ) {}

    /** Sum of all jiffie counters. */
    public function total(): int
    {
        return $this->user + $this->nice + $this->system
             + $this->idle + $this->iowait + $this->irq + $this->softirq;
    }

    /** Active (non-idle) jiffies. */
    public function active(): int
    {
        return $this->total() - $this->idle - $this->iowait;
    }
}
