<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * ADB device entry returned by `host:devices`.
 *
 * PHP 8.3: readonly class — all properties implicitly readonly.
 *
 * @since PHP 8.3
 */
readonly class Device
{
    /**
     * @param string $id   Serial number (e.g. "emulator-5554" or "192.168.1.10:5555").
     * @param string $type Connection state: "device" | "offline" | "unauthorized" | …
     */
    public function __construct(
        public string $id,
        public string $type,
    ) {}

    public function __toString(): string
    {
        return "{$this->id}\t{$this->type}";
    }
}
