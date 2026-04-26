<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Support;

/**
 * PHP stream wrapper that routes reads/writes through MockAdbSocket queues.
 *
 * Registered as stream wrapper "mockstream://".
 * Usage: $resource = fopen('mockstream://test', 'r+b');
 */
final class MockStream
{
    /** @var resource */
    public mixed $context;

    private static ?MockAdbSocket $mock = null;
    private static bool           $registered = false;

    private string $buffer = '';
    private bool   $eof    = false;

    public static function register(): void
    {
        if (!self::$registered) {
            stream_wrapper_register('mockstream', self::class);
            self::$registered = true;
        }
    }

    /**
     * Create a fake stream resource wired to the given MockAdbSocket.
     *
     * @return resource
     */
    public static function createPair(MockAdbSocket $mock): mixed
    {
        self::register();
        self::$mock = $mock;
        return fopen('mockstream://test', 'r+b');
    }

    // -------------------------------------------------------------------------
    // Stream wrapper protocol
    // -------------------------------------------------------------------------

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        if (self::$mock === null) {
            return '';
        }
        $data = self::$mock->dequeueReceive($count);
        if ($data === '' && !self::$mock->hasData()) {
            $this->eof = true;
        }
        return $data;
    }

    public function stream_write(string $data): int
    {
        if (self::$mock !== null) {
            self::$mock->logSend($data);
        }
        return strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->eof || (self::$mock !== null && !self::$mock->hasData());
    }

    public function stream_close(): void
    {
        // no-op
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return true;
    }

    public function stream_flush(): bool
    {
        return true;
    }
}
