<?php

declare(strict_types=1);

namespace AdbPhp\Logcat;

/**
 * A single parsed logcat binary log entry.
 *
 * PHP 8.3: readonly class + typed class constants.
 *
 * @since PHP 8.3
 */
readonly class LogcatEntry
{
    // -------------------------------------------------------------------------
    // Typed class constants (PHP 8.3)
    // -------------------------------------------------------------------------

    public const int PRIORITY_VERBOSE = 2;
    public const int PRIORITY_DEBUG   = 3;
    public const int PRIORITY_INFO    = 4;
    public const int PRIORITY_WARN    = 5;
    public const int PRIORITY_ERROR   = 6;
    public const int PRIORITY_FATAL   = 7;
    public const int PRIORITY_SILENT  = 8;

    /** @var array<int,string> */
    private const array PRIORITY_LABELS = [
        self::PRIORITY_VERBOSE => 'V',
        self::PRIORITY_DEBUG   => 'D',
        self::PRIORITY_INFO    => 'I',
        self::PRIORITY_WARN    => 'W',
        self::PRIORITY_ERROR   => 'E',
        self::PRIORITY_FATAL   => 'F',
        self::PRIORITY_SILENT  => 'S',
    ];

    public function __construct(
        public int    $date,      // Unix timestamp (seconds)
        public int    $pid,
        public int    $tid,
        public int    $priority,
        public string $tag,
        public string $message,
    ) {}

    /** Returns the single-character priority label: V/D/I/W/E/F/S. */
    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? '?';
    }

    public function __toString(): string
    {
        return sprintf(
            '%s/%s(%d): %s',
            $this->priorityLabel(),
            $this->tag,
            $this->pid,
            $this->message,
        );
    }
}
