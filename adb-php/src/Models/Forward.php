<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * A port-forward rule returned by `listForwards()`.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class Forward
{
    public function __construct(
        public string $serial,
        public string $local,
        public string $remote,
    ) {}
}
