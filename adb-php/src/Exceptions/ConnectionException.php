<?php

declare(strict_types=1);

namespace AdbPhp\Exceptions;

/**
 * Thrown when the TCP connection to the ADB server fails.
 *
 * @since PHP 8.3
 */
class ConnectionException extends AdbException {}
