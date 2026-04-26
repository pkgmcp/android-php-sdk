<?php

declare(strict_types=1);

namespace AdbPhp\Models;

/**
 * A single directory entry returned by `readdir()` / `sync.stat()`.
 *
 * PHP 8.3: readonly class.
 *
 * @since PHP 8.3
 */
readonly class FileEntry
{
    public function __construct(
        public string $name,
        public int    $mode,
        public int    $size,
        public int    $mtime,
    ) {}

    /** True when this entry is a directory. */
    public function isDirectory(): bool
    {
        return ($this->mode & 0o170000) === 0o040000;
    }

    /** True when this entry is a regular file. */
    public function isFile(): bool
    {
        return ($this->mode & 0o170000) === 0o100000;
    }

    /** True when this entry is a symbolic link. */
    public function isSymlink(): bool
    {
        return ($this->mode & 0o170000) === 0o120000;
    }
}
