<?php

declare(strict_types=1);

namespace AdbPhp\ProcStat;

use AdbPhp\Models\CpuStats;

/**
 * Parses the output of `cat /proc/stat` from an Android device.
 *
 * Mirrors `openProcStat()` from adbkit.
 *
 * PHP 8.3: readonly class, named arguments.
 *
 * @since PHP 8.3
 */
final class ProcStat
{
    /**
     * Aggregate CPU statistics (all cores combined).
     */
    public readonly CpuStats $cpu;

    /**
     * Per-core statistics: `['cpu0' => CpuStats, 'cpu1' => CpuStats, …]`.
     *
     * @var array<string, CpuStats>
     */
    public readonly array $cores;

    private function __construct(CpuStats $cpu, array $cores)
    {
        $this->cpu   = $cpu;
        $this->cores = $cores;
    }

    /**
     * Parse the raw text of `/proc/stat`.
     *
     * @param string $raw Shell output of `cat /proc/stat`.
     *
     * @throws \InvalidArgumentException
     */
    public static function parse(string $raw): self
    {
        $cpu   = null;
        $cores = [];

        foreach (explode("\n", trim($raw)) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (count($parts) < 5) {
                continue;
            }

            $name = array_shift($parts);
            if (!str_starts_with($name, 'cpu')) {
                break;
            }

            $stats = new CpuStats(
                user:    (int) ($parts[0] ?? 0),
                nice:    (int) ($parts[1] ?? 0),
                system:  (int) ($parts[2] ?? 0),
                idle:    (int) ($parts[3] ?? 0),
                iowait:  (int) ($parts[4] ?? 0),
                irq:     (int) ($parts[5] ?? 0),
                softirq: (int) ($parts[6] ?? 0),
            );

            if ($name === 'cpu') {
                $cpu = $stats;
            } else {
                $cores[$name] = $stats;
            }
        }

        if ($cpu === null) {
            throw new \InvalidArgumentException(
                'Failed to parse /proc/stat: no aggregate CPU line found.',
            );
        }

        return new self($cpu, $cores);
    }
}
