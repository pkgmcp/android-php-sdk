<?php

declare(strict_types=1);

namespace FastbootPhp;

/**
 * Immutable value object representing a complete bootloader response.
 *
 * Mirrors the anonymous response object `{ text, dataSize }` in fastboot.js.
 *
 * PHP 8.3: readonly class — all properties are implicitly readonly.
 *
 * @since PHP 8.3
 */
readonly class CommandResponse
{
    /**
     * @param string      $text     Concatenated INFO / OKAY message text.
     * @param string|null $dataSize Hex-encoded data size from a DATA response,
     *                              or null when no data phase was signalled.
     */
    public function __construct(
        public string $text,
        public ?string $dataSize,
    ) {}
}
