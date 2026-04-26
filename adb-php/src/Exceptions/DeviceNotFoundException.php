<?php

declare(strict_types=1);

namespace AdbPhp\Exceptions;

/**
 * Thrown when the requested device serial is not found or not available.
 *
 * @since PHP 8.3
 */
class DeviceNotFoundException extends AdbException
{
    public function __construct(string $serial)
    {
        parent::__construct(message: "Device '{$serial}' not found or not online.");
    }
}
