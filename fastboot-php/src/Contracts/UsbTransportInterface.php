<?php

declare(strict_types=1);

namespace FastbootPhp\Contracts;

/**
 * Abstraction over the raw USB bulk-transfer transport used by fastboot.
 *
 * Implement this interface to plug in a real USB backend (e.g. libusb via FFI,
 * an ADB bridge, a TCP tunnel, etc.).
 *
 * @since PHP 8.3
 */
interface UsbTransportInterface
{
    /**
     * Open / claim the USB device.
     *
     * @throws \FastbootPhp\UsbError When the device cannot be opened.
     */
    public function open(): void;

    /**
     * Release / close the USB device.
     */
    public function close(): void;

    /**
     * Returns true when the device is open and ready for bulk transfers.
     */
    public function isConnected(): bool;

    /**
     * Perform a USB bulk-OUT transfer (host → device).
     *
     * @param string $data Raw bytes to send.
     *
     * @throws \FastbootPhp\UsbError On transfer failure.
     */
    public function transferOut(string $data): void;

    /**
     * Perform a USB bulk-IN transfer (device → host).
     *
     * @param int $maxLength Maximum bytes to receive.
     *
     * @return string Raw bytes received.
     *
     * @throws \FastbootPhp\UsbError On transfer failure.
     */
    public function transferIn(int $maxLength): string;

    /**
     * Reset the USB device (best-effort; implementations may no-op).
     */
    public function reset(): void;
}
