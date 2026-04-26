<?php

declare(strict_types=1);

namespace FastbootPhp;

/**
 * Exception for USB errors not directly thrown by the USB transport layer.
 *
 * Mirrors the `UsbError` class in fastboot.js.
 *
 * @since PHP 8.3
 */
class UsbError extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
