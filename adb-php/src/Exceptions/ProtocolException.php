<?php

declare(strict_types=1);

namespace AdbPhp\Exceptions;

/**
 * Thrown when the ADB server returns an unexpected / malformed response.
 *
 * @since PHP 8.3
 */
class ProtocolException extends AdbException {}
