<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Models;

use AdbPhp\Models\FileEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileEntry::class)]
final class FileEntryTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $e = new FileEntry(name: 'test.txt', mode: 0o100644, size: 1024, mtime: 1700000000);

        $this->assertSame('test.txt', $e->name);
        $this->assertSame(0o100644, $e->mode);
        $this->assertSame(1024, $e->size);
        $this->assertSame(1700000000, $e->mtime);
    }

    #[Test]
    #[DataProvider('fileModes')]
    public function fileTypeDetectionIsCorrect(int $mode, bool $isDir, bool $isFile, bool $isLink): void
    {
        $e = new FileEntry('name', $mode, 0, 0);
        $this->assertSame($isDir, $e->isDirectory());
        $this->assertSame($isFile, $e->isFile());
        $this->assertSame($isLink, $e->isSymlink());
    }

    public static function fileModes(): array
    {
        return [
            'regular file'  => [0o100644, false, true,  false],
            'directory'     => [0o040755, true,  false, false],
            'symlink'       => [0o120777, false, false, true],
            'unknown type'  => [0o060000, false, false, false],
        ];
    }

    #[Test]
    public function isReadonlyClass(): void
    {
        $r = new \ReflectionClass(FileEntry::class);
        $this->assertTrue($r->isReadOnly());
    }
}
