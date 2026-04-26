<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * Options for `DeviceClient::startActivity()` / `startService()`.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class StartActivityOptions
{
    /**
     * @param bool                 $wait      Block until the activity is started (-W).
     * @param bool                 $debug     Enable debug mode (-D).
     * @param bool                 $force     Force-stop before starting (-S).
     * @param string|null          $action    Intent action (e.g. android.intent.action.VIEW).
     * @param string|null          $data      Intent data URI.
     * @param string|null          $mimeType  Intent MIME type.
     * @param string|null          $category  Intent category.
     * @param string|null          $component Component name (pkg/class).
     * @param string|null          $flags     Intent flags (hex or int string).
     * @param array<string,string> $extras    Intent extras [key => value].
     */
    public function __construct(
        public bool    $wait      = false,
        public bool    $debug     = false,
        public bool    $force     = false,
        public ?string $action    = null,
        public ?string $data      = null,
        public ?string $mimeType  = null,
        public ?string $category  = null,
        public ?string $component = null,
        public ?string $flags     = null,
        public array   $extras    = [],
    ) {}
}
