<?php

declare(strict_types=1);

namespace FastbootPhp;

/**
 * Exception for errors returned by the bootloader, as well as high-level
 * fastboot errors resulting from bootloader responses.
 *
 * Mirrors the `FastbootError` class in fastboot.js.
 *
 * @since PHP 8.3
 */
class FastbootError extends \RuntimeException
{
    public function __construct(
        public readonly string $status,
        public readonly string $bootloaderMessage,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: "Bootloader replied with {$status}: {$bootloaderMessage}",
            previous: $previous,
        );
    }
}
