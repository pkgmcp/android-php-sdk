<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit;

use FastbootPhp\FastbootError;
use FastbootPhp\Sparse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sparse::class)]
final class SparseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // isSparse
    // -------------------------------------------------------------------------

    #[Test]
    public function isSparseReturnsTrueForSparseImage(): void
    {
        $raw    = str_repeat('A', 4096);
        $sparse = Sparse::toSparse($raw);

        $this->assertTrue(Sparse::isSparse($sparse));
    }

    #[Test]
    public function isSparseReturnsFalseForRawData(): void
    {
        $this->assertFalse(Sparse::isSparse(str_repeat("\x00", 4096)));
    }

    #[Test]
    public function isSparseReturnsFalseForShortData(): void
    {
        $this->assertFalse(Sparse::isSparse('short'));
    }

    // -------------------------------------------------------------------------
    // toSparse
    // -------------------------------------------------------------------------

    #[Test]
    public function toSparseProducesValidMagic(): void
    {
        $sparse = Sparse::toSparse(str_repeat("\xff", 512));
        $magic  = unpack('V', substr($sparse, 0, 4))[1];

        $this->assertSame(Sparse::SPARSE_HEADER_MAGIC, $magic);
    }

    #[Test]
    public function toSparseHeaderSizeIsCorrect(): void
    {
        $sparse = Sparse::toSparse(str_repeat('X', 100));
        $this->assertGreaterThanOrEqual(Sparse::SPARSE_HEADER_SIZE + Sparse::CHUNK_HEADER_SIZE, strlen($sparse));
    }

    #[Test]
    #[DataProvider('blockSizes')]
    public function toSparsePadsToBlockBoundary(int $dataSize, int $blockSize): void
    {
        $sparse    = Sparse::toSparse(str_repeat("\x42", $dataSize), $blockSize);
        $numBlocks = (int) ceil($dataSize / $blockSize);

        // The data portion should equal numBlocks * blockSize
        $dataLen = strlen($sparse) - Sparse::SPARSE_HEADER_SIZE - Sparse::CHUNK_HEADER_SIZE;
        $this->assertSame($numBlocks * $blockSize, $dataLen);
    }

    public static function blockSizes(): array
    {
        return [
            'exact block'    => [4096, 4096],
            'partial block'  => [1000, 4096],
            'multi-block'    => [8192, 4096],
            'small block'    => [512,  512],
        ];
    }

    #[Test]
    public function roundTripRawToSparsePreservesIsSparse(): void
    {
        $raw    = random_bytes(8192);
        $sparse = Sparse::toSparse($raw);
        $this->assertTrue(Sparse::isSparse($sparse));
    }

    // -------------------------------------------------------------------------
    // split
    // -------------------------------------------------------------------------

    #[Test]
    public function splitSingleChunkFitsInLimit(): void
    {
        $raw    = str_repeat('Z', 1024);
        $sparse = Sparse::toSparse($raw, 512);
        $parts  = Sparse::split($sparse, 1024 * 1024);

        $this->assertCount(1, $parts);
        $this->assertTrue(Sparse::isSparse($parts[0]));
    }

    #[Test]
    public function splitProducesMultiplePartsWhenLimitIsSmall(): void
    {
        // Create a 64 KiB image so we can split it
        $raw    = str_repeat('A', 65536);
        $sparse = Sparse::toSparse($raw, 4096);

        // Limit to 32 KiB per part → expect 2 parts
        $parts = Sparse::split($sparse, 32768);

        $this->assertGreaterThanOrEqual(2, count($parts));
        foreach ($parts as $part) {
            $this->assertTrue(Sparse::isSparse($part));
            $this->assertLessThanOrEqual(32768 + 4096, strlen($part)); // slight headroom
        }
    }

    #[Test]
    public function splitThrowsOnNonSparseInput(): void
    {
        $this->expectException(FastbootError::class);
        Sparse::split(str_repeat("\x00", 1024), 512);
    }

    // -------------------------------------------------------------------------
    // Typed constants
    // -------------------------------------------------------------------------

    #[Test]
    public function typedConstantsAreInts(): void
    {
        $this->assertIsInt(Sparse::SPARSE_HEADER_MAGIC);
        $this->assertIsInt(Sparse::CHUNK_TYPE_RAW);
        $this->assertIsInt(Sparse::CHUNK_TYPE_FILL);
        $this->assertIsInt(Sparse::CHUNK_TYPE_DONT_CARE);
        $this->assertIsInt(Sparse::CHUNK_TYPE_CRC32);
        $this->assertSame(28, Sparse::SPARSE_HEADER_SIZE);
        $this->assertSame(12, Sparse::CHUNK_HEADER_SIZE);
    }
}
