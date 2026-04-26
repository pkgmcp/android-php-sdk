<?php

declare(strict_types=1);

namespace FastbootPhp;

/**
 * Shared logging and utility helpers.
 *
 * Mirrors `common.js` from fastboot.js.
 *
 * Debug levels (PHP 8.3 typed class constants):
 *   LEVEL_SILENT  = 0 — no output
 *   LEVEL_DEBUG   = 1 — recommended for general use
 *   LEVEL_VERBOSE = 2 — for debugging only
 *
 * @since PHP 8.3
 */
final class Common
{
    /** @var int Suppress all log output. */
    public const int LEVEL_SILENT  = 0;

    /** @var int Standard debug output. Recommended for general use. */
    public const int LEVEL_DEBUG   = 1;

    /** @var int Verbose output. For deep debugging only. */
    public const int LEVEL_VERBOSE = 2;

    private static int $debugLevel = self::LEVEL_SILENT;

    /**
     * Change the global debug level.
     *
     * Mirrors `setDebugLevel(level)` from fastboot.js global scope.
     *
     * @param int $level {@see Common::LEVEL_SILENT} | {@see Common::LEVEL_DEBUG} | {@see Common::LEVEL_VERBOSE}
     */
    public static function setDebugLevel(int $level): void
    {
        self::$debugLevel = max(self::LEVEL_SILENT, min(self::LEVEL_VERBOSE, $level));
    }

    /** Returns the current debug level. */
    public static function getDebugLevel(): int
    {
        return self::$debugLevel;
    }

    /**
     * Emit a debug-level message (level ≥ 1).
     *
     * @param string $message   Log message.
     * @param mixed  ...$context Optional context values serialised as JSON.
     */
    public static function logDebug(string $message, mixed ...$context): void
    {
        if (self::$debugLevel >= self::LEVEL_DEBUG) {
            self::emit('DEBUG', $message, $context);
        }
    }

    /**
     * Emit a verbose-level message (level ≥ 2).
     *
     * @param string $message   Log message.
     * @param mixed  ...$context Optional context values serialised as JSON.
     */
    public static function logVerbose(string $message, mixed ...$context): void
    {
        if (self::$debugLevel >= self::LEVEL_VERBOSE) {
            self::emit('VERBOSE', $message, $context);
        }
    }

    // -------------------------------------------------------------------------

    /** @param mixed[] $context */
    private static function emit(string $level, string $message, array $context): void
    {
        $parts = ["[fastboot-php] [{$level}] {$message}"];
        foreach ($context as $item) {
            $parts[] = is_string($item)
                ? $item
                : json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        fwrite(STDERR, implode(' ', $parts) . PHP_EOL);
    }

    /** Non-instantiable utility class. */
    private function __construct() {}
}
