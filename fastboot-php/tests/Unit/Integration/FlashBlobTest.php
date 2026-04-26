<?php

declare(strict_types=1);

namespace FastbootPhp\Tests\Unit\Integration;

use FastbootPhp\FastbootDevice;
use FastbootPhp\FastbootError;
use FastbootPhp\Sparse;
use FastbootPhp\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end flashBlob() tests — covers the full chain:
 *   getVariable(has-slot) → getVariable(current-slot) →
 *   getVariable(max-download-size) → raw→sparse conversion →
 *   Sparse::split → upload(download: + payload) → flash:partition
 */
#[CoversClass(FastbootDevice::class)]
final class FlashBlobTest extends TestCase
{
    private MockTransport $mock;
    private FastbootDevice $device;

    protected function setUp(): void
    {
        $this->mock   = new MockTransport();
        $this->device = new FastbootDevice($this->mock);
        $this->device->connect();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Queue a getvar response. */
    private function queueVar(string $value): void
    {
        $this->mock->queueResponse("OKAY{$value}");
    }

    /** Queue a DATA + OKAY sequence for a single upload pass. */
    private function queueUpload(int $size): void
    {
        $hex = str_pad(dechex($size), 8, '0', STR_PAD_LEFT);
        $this->mock->queueResponse("DATA{$hex}");  // download: response
        $this->mock->queueResponse('OKAY');          // after raw payload transfer
    }

    /** Queue an OKAY response for flash:<partition>. */
    private function queueFlash(): void
    {
        $this->mock->queueResponse('OKAY');
    }

    // -------------------------------------------------------------------------
    // Non-AB partition, raw image → sparse, single pass
    // -------------------------------------------------------------------------

    #[Test]
    public function flashBlobRawImageSinglePass(): void
    {
        $rawImage = str_repeat('X', 4096); // 4 KiB raw

        // getvar has-slot:boot → "no"
        $this->queueVar('no');
        // getvar max-download-size → 512 MiB
        $this->queueVar('20000000');
        // upload (download: + payload)
        $sparseImage = Sparse::toSparse($rawImage);
        $parts       = Sparse::split($sparseImage, 0x20000000);
        foreach ($parts as $part) {
            $this->queueUpload(strlen($part));
        }
        // flash:boot
        $this->queueFlash();

        $progress  = [];
        $this->device->flashBlob('boot', $rawImage, static function (float $p) use (&$progress): void {
            $progress[] = $p;
        });

        // Confirm progress reached 1.0
        $this->assertSame(1.0, end($progress));

        // Confirm flash:boot was sent
        $sent = $this->mock->getSentData();
        $this->assertContains('flash:boot', $sent);
    }

    // -------------------------------------------------------------------------
    // AB partition — resolves slot suffix
    // -------------------------------------------------------------------------

    #[Test]
    public function flashBlobResolvesAbSlot(): void
    {
        $rawImage = str_repeat('A', 4096);

        // getvar has-slot:system → "yes"
        $this->queueVar('yes');
        // getvar current-slot → "b"
        $this->queueVar('b');
        // getvar max-download-size
        $this->queueVar('20000000');
        // upload
        $sparse = Sparse::toSparse($rawImage);
        $this->queueUpload(strlen($sparse));
        // flash:system_b
        $this->queueFlash();

        $this->device->flashBlob('system', $rawImage);

        $sent = $this->mock->getSentData();
        $this->assertContains('flash:system_b', $sent);
    }

    // -------------------------------------------------------------------------
    // Sparse image split across multiple passes
    // -------------------------------------------------------------------------

    #[Test]
    public function flashBlobSplitsSparseImageIntoPasses(): void
    {
        // 128 KiB raw → sparse → split at 64 KiB → 2 passes
        $rawImage = str_repeat('B', 131072);
        $sparse   = Sparse::toSparse($rawImage, 4096);
        $limit    = 65536; // 64 KiB
        $parts    = Sparse::split($sparse, $limit);

        // getvar has-slot:vendor → "no"
        $this->queueVar('no');
        // getvar max-download-size → 64 KiB
        $this->mock->queueResponse('OKAY00010000');  // 65536 in hex

        foreach ($parts as $part) {
            $this->queueUpload(strlen($part));
            $this->queueFlash();
        }

        $this->device->flashBlob('vendor', $rawImage);

        // Each pass sends: download: + flash:vendor
        $sent = $this->mock->getSentData();
        $flashCount = count(array_filter($sent, static fn(string $s) => $s === 'flash:vendor'));
        $this->assertGreaterThanOrEqual(2, $flashCount);
    }

    // -------------------------------------------------------------------------
    // Already-sparse image passed directly
    // -------------------------------------------------------------------------

    #[Test]
    public function flashBlobPassesThroughSparseImage(): void
    {
        $rawImage   = str_repeat('S', 4096);
        $sparseImage = Sparse::toSparse($rawImage);

        $this->queueVar('no');        // has-slot
        $this->queueVar('20000000');  // max-download-size
        $this->queueUpload(strlen($sparseImage));
        $this->queueFlash();

        $this->device->flashBlob('recovery', $sparseImage);

        $sent = $this->mock->getSentData();
        $this->assertContains('flash:recovery', $sent);
    }

    // -------------------------------------------------------------------------
    // Progress callback fires between 0 and 1
    // -------------------------------------------------------------------------

    #[Test]
    public function flashBlobProgressCallbackRange(): void
    {
        $rawImage = str_repeat('P', 4096);

        $this->queueVar('no');
        $this->queueVar('20000000');
        $sparse = Sparse::toSparse($rawImage);
        $this->queueUpload(strlen($sparse));
        $this->queueFlash();

        $values = [];
        $this->device->flashBlob('dtbo', $rawImage, static function (float $p) use (&$values): void {
            $values[] = $p;
        });

        foreach ($values as $p) {
            $this->assertGreaterThanOrEqual(0.0, $p);
            $this->assertLessThanOrEqual(1.0, $p);
        }
        $this->assertSame(1.0, end($values));
    }

    // -------------------------------------------------------------------------
    // Erase + Lock + Unlock + Reboot chain
    // -------------------------------------------------------------------------

    #[Test]
    public function fullFlashAndBootChain(): void
    {
        $raw = str_repeat('C', 4096);

        // Flash boot
        $this->queueVar('no');
        $this->queueVar('20000000');
        $this->queueUpload(strlen(Sparse::toSparse($raw)));
        $this->queueFlash();

        // Erase userdata
        $this->mock->queueResponse('OKAY');

        // Unlock
        $this->mock->queueResponse('OKAY');

        // Reboot
        $this->mock->queueResponse('OKAY');

        $this->device->flashBlob('boot', $raw);
        $this->device->erase('userdata');
        $this->device->unlock();
        $this->device->reboot();

        $sent = $this->mock->getSentData();
        $this->assertContains('flash:boot', $sent);
        $this->assertContains('erase:userdata', $sent);
        $this->assertContains('flashing unlock', $sent);
        $this->assertContains('reboot', $sent);
    }
}
