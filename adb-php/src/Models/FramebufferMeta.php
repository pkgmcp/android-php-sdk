<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * Framebuffer metadata returned alongside a framebuffer stream.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class FramebufferMeta
{
    public function __construct(
        public int    $version,
        public int    $bpp,
        public int    $size,
        public int    $width,
        public int    $height,
        public int    $redOffset,
        public int    $redLength,
        public int    $blueOffset,
        public int    $blueLength,
        public int    $greenOffset,
        public int    $greenLength,
        public int    $alphaOffset,
        public int    $alphaLength,
        public string $format,
    ) {}
}
