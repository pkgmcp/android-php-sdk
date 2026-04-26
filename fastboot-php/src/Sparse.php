<?php

declare(strict_types=1);

namespace FastbootPhp;

/**
 * Android sparse-image utilities.
 *
 * Mirrors `sparse.js` from fastboot.js.
 *
 * PHP 8.3: typed class constants.
 *
 * @since PHP 8.3
 */
final class Sparse
{
    // -------------------------------------------------------------------------
    // Typed class constants (PHP 8.3)
    // -------------------------------------------------------------------------

    /** Sparse image file magic number. */
    public const int SPARSE_HEADER_MAGIC  = 0xED26FF3A;

    /** File header size in bytes. */
    public const int SPARSE_HEADER_SIZE   = 28;

    /** Chunk header size in bytes. */
    public const int CHUNK_HEADER_SIZE    = 12;

    /** RAW chunk — raw data follows. */
    public const int CHUNK_TYPE_RAW       = 0xCAC1;

    /** FILL chunk — 4-byte fill value repeated to cover the chunk. */
    public const int CHUNK_TYPE_FILL      = 0xCAC2;

    /** DONT_CARE chunk — skip / zero fill. */
    public const int CHUNK_TYPE_DONT_CARE = 0xCAC3;

    /** CRC32 chunk — checksum (ignored by most bootloaders). */
    public const int CHUNK_TYPE_CRC32     = 0xCAC4;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns true when $data starts with the sparse-image magic number.
     */
    public static function isSparse(string $data): bool
    {
        if (strlen($data) < self::SPARSE_HEADER_SIZE) {
            return false;
        }
        /** @var array{1: int} $u */
        $u = unpack('V', substr($data, 0, 4));
        return $u[1] === self::SPARSE_HEADER_MAGIC;
    }

    /**
     * Convert a raw partition image into a single-chunk sparse image.
     *
     * @param string $rawData   Raw partition image bytes.
     * @param int    $blockSize Sparse block size in bytes (default 4096).
     *
     * @return string Sparse-formatted image bytes.
     */
    public static function toSparse(string $rawData, int $blockSize = 4096): string
    {
        $totalSize = strlen($rawData);
        $numBlocks = (int) ceil($totalSize / $blockSize);

        // File header (28 bytes)
        $fileHeader = pack(
            'VvvvvVVVV',
            self::SPARSE_HEADER_MAGIC,  // magic
            1,                          // major_version
            0,                          // minor_version
            self::SPARSE_HEADER_SIZE,   // file_hdr_sz
            self::CHUNK_HEADER_SIZE,    // chunk_hdr_sz
            $blockSize,                 // blk_sz
            $numBlocks,                 // total_blks
            1,                          // total_chunks
            0,                          // image_checksum (ignored)
        );

        // Pad raw data to block boundary
        $paddedSize = $numBlocks * $blockSize;
        $paddedData = str_pad($rawData, $paddedSize, "\x00");

        // Chunk header (12 bytes)
        $chunkHeader = pack(
            'vvVV',
            self::CHUNK_TYPE_RAW,
            0,                                    // reserved
            $numBlocks,                           // chunk_sz (blocks)
            self::CHUNK_HEADER_SIZE + $paddedSize, // total_sz
        );

        return $fileHeader . $chunkHeader . $paddedData;
    }

    /**
     * Split an oversized sparse image into sub-images each ≤ $maxDownloadSize.
     *
     * @param string $sparseData      Sparse-formatted image bytes.
     * @param int    $maxDownloadSize Maximum bytes per output chunk.
     *
     * @return list<string> Array of sparse-image byte strings.
     *
     * @throws FastbootError When the image is malformed.
     */
    public static function split(string $sparseData, int $maxDownloadSize): array
    {
        if (!self::isSparse($sparseData)) {
            throw new FastbootError('FAIL', 'Cannot split a non-sparse image.');
        }

        $offset = 0;
        $fhdr   = self::parseFileHeader($sparseData, $offset);
        $offset += $fhdr['file_hdr_sz'];

        $numChunks     = $fhdr['total_chunks'];
        $outputImages  = [];
        $currentChunks = [];
        $currentBlocks = 0;
        $currentBytes  = self::SPARSE_HEADER_SIZE;

        for ($i = 0; $i < $numChunks; $i++) {
            if ($offset + self::CHUNK_HEADER_SIZE > strlen($sparseData)) {
                throw new FastbootError('FAIL', "Sparse image truncated before chunk {$i}.");
            }

            $chdr       = self::parseChunkHeader($sparseData, $offset);
            $dataSize   = $chdr['total_sz'] - $fhdr['chunk_hdr_sz'];
            $chunkData  = substr($sparseData, $offset + $fhdr['chunk_hdr_sz'], $dataSize);
            $chunkBytes = $fhdr['chunk_hdr_sz'] + $dataSize;

            if ($currentChunks !== [] && ($currentBytes + $chunkBytes) > $maxDownloadSize) {
                $outputImages[]  = self::assembleImage($fhdr, $currentChunks, $currentBlocks);
                $currentChunks   = [];
                $currentBlocks   = 0;
                $currentBytes    = self::SPARSE_HEADER_SIZE;
            }

            $currentChunks[] = ['header' => $chdr, 'data' => $chunkData, 'hdr_sz' => $fhdr['chunk_hdr_sz']];
            $currentBlocks  += $chdr['chunk_sz'];
            $currentBytes   += $chunkBytes;
            $offset         += $chdr['total_sz'];
        }

        if ($currentChunks !== []) {
            $outputImages[] = self::assembleImage($fhdr, $currentChunks, $currentBlocks);
        }

        return $outputImages;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array<string,int> */
    private static function parseFileHeader(string $data, int $offset): array
    {
        /** @var array<string,int> $u */
        $u = unpack(
            'Vmagic/vmajor/vminor/vfile_hdr_sz/vchunk_hdr_sz/Vblk_sz/Vtotal_blks/Vtotal_chunks/Vimage_checksum',
            substr($data, $offset, self::SPARSE_HEADER_SIZE),
        );
        return $u;
    }

    /** @return array<string,int> */
    private static function parseChunkHeader(string $data, int $offset): array
    {
        /** @var array<string,int> $u */
        $u = unpack(
            'vchunk_type/vreserved/Vchunk_sz/Vtotal_sz',
            substr($data, $offset, self::CHUNK_HEADER_SIZE),
        );
        return $u;
    }

    /**
     * @param array<string,int>          $fhdr
     * @param list<array<string,mixed>>  $chunks
     */
    private static function assembleImage(array $fhdr, array $chunks, int $totalBlocks): string
    {
        $fileHeader = pack(
            'VvvvvVVVV',
            self::SPARSE_HEADER_MAGIC,
            $fhdr['major'],
            $fhdr['minor'],
            $fhdr['file_hdr_sz'],
            $fhdr['chunk_hdr_sz'],
            $fhdr['blk_sz'],
            $totalBlocks,
            count($chunks),
            0,
        );

        $body = '';
        foreach ($chunks as $chunk) {
            $chdr    = $chunk['header'];
            $cdata   = $chunk['data'];
            $totalSz = $chunk['hdr_sz'] + strlen($cdata);

            $body .= pack('vvVV', $chdr['chunk_type'], $chdr['reserved'], $chdr['chunk_sz'], $totalSz);
            $body .= $cdata;
        }

        return $fileHeader . $body;
    }

    /** Non-instantiable utility class. */
    private function __construct() {}
}
