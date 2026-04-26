<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * Device entry including its USB/transport path.
 * Returned by `listDevicesWithPaths()`.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class DeviceWithPath
{
    public function __construct(
        public string $id,
        public string $type,
        public string $path,
    ) {}
}
