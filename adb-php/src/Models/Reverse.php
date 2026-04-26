<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * A reverse port-forward rule returned by `listReverses()`.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class Reverse
{
    public function __construct(
        public string $remote,
        public string $local,
    ) {}
}
