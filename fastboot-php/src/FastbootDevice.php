<?php

declare(strict_types=1);

namespace FastbootPhp;

use FastbootPhp\Contracts\UsbTransportInterface;

/**
 * Client for executing fastboot commands and operations on a device.
 *
 * Mirrors the `FastbootDevice` class from kdrag0n/fastboot.js.
 *
 * PHP 8.3: typed class constants, named arguments, #[Override].
 *
 * @since PHP 8.3
 */
class FastbootDevice
{
    // -------------------------------------------------------------------------
    // Typed class constants (PHP 8.3)
    // -------------------------------------------------------------------------

    /** Maximum single bulk-OUT transfer in bytes. */
    public const int BULK_TRANSFER_SIZE = 16384;

    /** Default max download buffer when the bootloader does not advertise one (512 MiB). */
    public const int DEFAULT_DOWNLOAD_SIZE = 512 * 1024 * 1024;

    /**
     * Hard cap to conserve RAM (1 GiB).
     * Mirrors MAX_DOWNLOAD_SIZE in fastboot.js.
     */
    public const int MAX_DOWNLOAD_SIZE = 1024 * 1024 * 1024;

    /** getvar query timeout (ms) — informational only; PHP I/O is synchronous. */
    public const int GETVAR_TIMEOUT_MS = 10_000;

    // -------------------------------------------------------------------------

    private ?UsbTransportInterface $transport;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * Create a new FastbootDevice.
     *
     * No USB connection is opened here; call {@see connect()} to do so.
     *
     * @param UsbTransportInterface|null $transport USB transport implementation.
     *   Pass null and call {@see setTransport()} before {@see connect()}.
     */
    public function __construct(?UsbTransportInterface $transport = null)
    {
        $this->transport = $transport;
    }

    // -------------------------------------------------------------------------
    // Transport
    // -------------------------------------------------------------------------

    /** Replace the transport (e.g. swap in a mock during tests). */
    public function setTransport(UsbTransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    // -------------------------------------------------------------------------
    // Connection lifecycle
    // -------------------------------------------------------------------------

    /**
     * Returns whether a USB device is connected and ready.
     *
     * Mirrors `FastbootDevice.isConnected` getter.
     */
    public function isConnected(): bool
    {
        return $this->transport !== null && $this->transport->isConnected();
    }

    /**
     * Open the USB transport and claim the fastboot interface.
     *
     * Mirrors `FastbootDevice.connect()`.
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function connect(): void
    {
        $this->requireTransport();
        Common::logDebug('Opening USB transport …');
        $this->transport->open();
        Common::logDebug('USB device opened.');
    }

    /** Disconnect from the USB device. */
    public function disconnect(): void
    {
        if ($this->transport !== null && $this->transport->isConnected()) {
            $this->transport->close();
            Common::logDebug('USB device disconnected.');
        }
    }

    // -------------------------------------------------------------------------
    // Low-level protocol
    // -------------------------------------------------------------------------

    /**
     * Send a textual command to the bootloader and return the full response.
     *
     * This is in raw fastboot format, **not** AOSP fastboot CLI syntax.
     *
     * Mirrors `FastbootDevice.runCommand()`.
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function runCommand(string $command): CommandResponse
    {
        $this->requireConnected();
        Common::logDebug("Sending command: {$command}");
        $this->transport->transferOut($command);
        return $this->readResponse();
    }

    /**
     * Retrieve the value of a bootloader variable.
     *
     * Mirrors `FastbootDevice.getVariable()`.
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function getVariable(string $varName): ?string
    {
        try {
            $value = trim($this->runCommand("getvar:{$varName}")->text);
            Common::logDebug("getvar:{$varName} = {$value}");
            return $value !== '' ? $value : null;
        } catch (FastbootError $e) {
            if ($e->status === 'FAIL') {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Return the maximum single-download size the bootloader accepts.
     *
     * Mirrors `FastbootDevice.getMaxDownloadSize()`.
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function getMaxDownloadSize(): int
    {
        $raw = $this->getVariable('max-download-size');
        if ($raw === null) {
            return self::DEFAULT_DOWNLOAD_SIZE;
        }
        $size = (int) hexdec(ltrim($raw, '0x'));
        return $size > 0 ? min($size, self::MAX_DOWNLOAD_SIZE) : self::DEFAULT_DOWNLOAD_SIZE;
    }

    // -------------------------------------------------------------------------
    // Upload
    // -------------------------------------------------------------------------

    /**
     * Upload a raw payload to the bootloader.
     *
     * Does not handle sparse conversion or flashing; use {@see flashBlob()} for that.
     *
     * Mirrors `FastbootDevice.upload()`.
     *
     * @param callable|null $onProgress `function(float $progress): void` — 0.0–1.0
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function upload(string $partition, string $data, ?callable $onProgress = null): void
    {
        $this->requireConnected();
        $onProgress ??= static fn(float $_p) => null;

        $size    = strlen($data);
        $xferHex = str_pad(dechex($size), 8, '0', STR_PAD_LEFT);

        if (strlen($xferHex) !== 8) {
            throw new FastbootError(
                status: 'FAIL',
                bootloaderMessage: "Transfer size overflow: {$xferHex} is more than 8 digits",
            );
        }

        Common::logDebug("Uploading {$size} bytes to {$partition} ({$xferHex})");

        $downloadResp = $this->runCommand("download:{$xferHex}");
        if ($downloadResp->dataSize === null) {
            throw new FastbootError(
                status: 'FAIL',
                bootloaderMessage: "Unexpected download response: {$downloadResp->text}",
            );
        }

        $expected = (int) hexdec($downloadResp->dataSize);
        if ($expected !== $size) {
            throw new FastbootError(
                status: 'FAIL',
                bootloaderMessage: "Bootloader wants {$expected} bytes, sending {$size}",
            );
        }

        $this->sendRawPayload($data, $onProgress);
        Common::logDebug('Upload complete: ' . $this->readResponse()->text);
    }

    // -------------------------------------------------------------------------
    // Flashing
    // -------------------------------------------------------------------------

    /**
     * Flash an image to a named partition.
     *
     * Handles A/B slots, raw→sparse conversion, and oversized image splitting.
     *
     * Mirrors `FastbootDevice.flashBlob()`.
     *
     * @param callable|null $onProgress `function(float $progress): void`
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function flashBlob(string $partition, string $data, ?callable $onProgress = null): void
    {
        $onProgress ??= static fn(float $_p) => null;

        // Resolve A/B slot
        if ($this->getVariable("has-slot:{$partition}") === 'yes') {
            $slot      = $this->getVariable('current-slot') ?? 'a';
            $partition = "{$partition}_{$slot}";
            Common::logDebug("A/B → resolved partition: {$partition}");
        }

        $maxSize = $this->getMaxDownloadSize();
        Common::logDebug("Max download size: {$maxSize}");

        if (!Sparse::isSparse($data)) {
            Common::logDebug('Converting raw image to sparse …');
            $data = Sparse::toSparse($data);
        }

        $parts = Sparse::split($data, $maxSize);
        $total = count($parts);
        Common::logDebug("Flashing {$partition} in {$total} pass(es)");

        foreach ($parts as $i => $part) {
            $passProgress = static fn(float $p) => $onProgress(($i + $p) / $total);
            $this->upload($partition, $part, $passProgress);
            $this->runCommand("flash:{$partition}");
        }

        $onProgress(1.0);
    }

    /**
     * Erase a partition.
     *
     * @throws UsbError
     * @throws FastbootError
     */
    public function erase(string $partition): void
    {
        $this->runCommand("erase:{$partition}");
    }

    // -------------------------------------------------------------------------
    // Factory ZIP
    // -------------------------------------------------------------------------

    /**
     * Flash a full AOSP factory image ZIP (update.zip).
     *
     * Mirrors `flashZip()` / `factory.js` from fastboot.js.
     *
     * @param callable|null $onProgress `function(string $action, string $item, float $progress): void`
     *
     * @throws UsbError
     * @throws FastbootError
     * @throws \InvalidArgumentException
     */
    public function flashFactoryZip(
        string   $zipData,
        bool     $wipe = false,
        ?callable $onProgress = null,
    ): void {
        $onProgress ??= static fn(string $_a, string $_i, float $_p) => null;

        $entries = $this->parseZip($zipData);

        $flashOrder = [
            'bootloader', 'radio',
            'boot', 'dtbo', 'vendor_boot',
            'system', 'system_ext', 'product', 'vendor', 'odm',
            'vbmeta', 'vbmeta_system', 'super',
        ];

        $total = count($flashOrder);

        foreach ($flashOrder as $idx => $name) {
            $key = array_key_exists($name, $entries)         ? $name
                 : (array_key_exists("{$name}.img", $entries) ? "{$name}.img" : null);

            if ($key === null) {
                Common::logDebug("Skipping absent partition: {$name}");
                continue;
            }

            $onProgress('flash', $name, $idx / $total);
            Common::logDebug("Flashing factory partition: {$name}");

            $this->flashBlob(
                partition:  $name,
                data:       $entries[$key],
                onProgress: static fn(float $p) => $onProgress('flash', $name, ($idx + $p) / $total),
            );

            if (in_array($name, ['bootloader', 'radio'], strict: true)) {
                $onProgress('reboot', $name, ($idx + 1) / $total);
                Common::logDebug("Rebooting after {$name} …");
                $this->rebootBootloader();
                sleep(5);
                $this->connect();
            }
        }

        if ($wipe) {
            $onProgress('wipe', 'userdata', 0.95);
            $this->erase('userdata');
            $this->erase('cache');
        }

        $onProgress('done', '', 1.0);
        $this->reboot();
    }

    // -------------------------------------------------------------------------
    // Lock / Unlock
    // -------------------------------------------------------------------------

    /** Lock the bootloader. */
    public function lock(): void
    {
        $this->runCommand('flashing lock');
    }

    /** Unlock the bootloader. */
    public function unlock(): void
    {
        $this->runCommand('flashing unlock');
    }

    // -------------------------------------------------------------------------
    // Reboot
    // -------------------------------------------------------------------------

    /** Reboot into the OS. */
    public function reboot(): void
    {
        $this->runCommand('reboot');
    }

    /** Reboot into bootloader mode. */
    public function rebootBootloader(): void
    {
        $this->runCommand('reboot-bootloader');
    }

    /** Reboot into recovery mode. */
    public function rebootRecovery(): void
    {
        $this->runCommand('reboot-recovery');
    }

    /** Reboot into userspace fastbootd (Android 10+). */
    public function rebootFastbootd(): void
    {
        $this->runCommand('reboot-fastboot');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Read a complete bootloader response from the transport.
     *
     * @throws UsbError
     * @throws FastbootError
     */
    private function readResponse(): CommandResponse
    {
        $text     = '';
        $dataSize = null;

        do {
            $packet     = $this->transport->transferIn(64);
            $respStatus = substr($packet, 0, 4);
            $respMsg    = substr($packet, 4);

            Common::logDebug("Response: {$respStatus} {$respMsg}");

            match ($respStatus) {
                'OKAY' => $text .= $respMsg,
                'INFO' => $text .= $respMsg . "\n",
                'DATA' => $dataSize = $respMsg,
                'FAIL' => throw new FastbootError(status: 'FAIL', bootloaderMessage: $respMsg),
                default => throw new FastbootError(
                    status: 'UNKNOWN',
                    bootloaderMessage: "Unknown status token: {$respStatus} {$respMsg}",
                ),
            };
        } while ($respStatus === 'INFO');

        return new CommandResponse(text: $text, dataSize: $dataSize);
    }

    /**
     * Send raw binary payload in BULK_TRANSFER_SIZE chunks.
     *
     * @param callable $onProgress `function(float $progress): void`
     *
     * @throws UsbError
     */
    private function sendRawPayload(string $data, callable $onProgress): void
    {
        $total     = strlen($data);
        $remaining = $total;
        $i         = 0;

        while ($remaining > 0) {
            $chunk    = substr($data, $i * self::BULK_TRANSFER_SIZE, self::BULK_TRANSFER_SIZE);
            $chunkLen = strlen($chunk);

            if ($i % 10 === 0) {
                $onProgress(($total - $remaining) / $total);
            }
            if ($i % 1000 === 0) {
                Common::logVerbose("  Sending {$chunkLen} bytes, {$remaining} remaining, i={$i}");
            }

            $this->transport->transferOut($chunk);
            $remaining -= $chunkLen;
            $i++;
        }

        $onProgress(1.0);
    }

    /**
     * Parse a ZIP archive into a filename → raw-bytes map.
     *
     * Uses PHP's built-in {@see \ZipArchive}.
     *
     * @return array<string,string>
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function parseZip(string $zipData): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fastboot_');
        if ($tmp === false) {
            throw new \RuntimeException('Failed to create temporary file for ZIP.');
        }

        try {
            file_put_contents($tmp, $zipData);
            $zip = new \ZipArchive();

            if ($zip->open($tmp) !== true) {
                throw new \InvalidArgumentException('Failed to open factory ZIP archive.');
            }

            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name   = $zip->getNameIndex($i);
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    Common::logDebug("Cannot read ZIP entry: {$name}");
                    continue;
                }
                $entries[$name] = stream_get_contents($stream);
                fclose($stream);
            }

            $zip->close();
            return $entries;
        } finally {
            @unlink($tmp);
        }
    }

    private function requireTransport(): void
    {
        if ($this->transport === null) {
            throw new \LogicException(
                'No USB transport set. Pass a UsbTransportInterface to the constructor or call setTransport().',
            );
        }
    }

    private function requireConnected(): void
    {
        $this->requireTransport();
        if (!$this->isConnected()) {
            throw new UsbError('No USB device connected. Call connect() first.');
        }
    }
}
