<?php

declare(strict_types=1);

namespace FastbootPhp\Transport;

use FastbootPhp\Contracts\UsbTransportInterface;

/**
 * In-memory mock transport for unit testing.
 *
 * Pre-load expected responses with {@see queueResponse()} and inspect what
 * was sent with {@see getSentData()}.
 *
 * PHP 8.3: #[Override] on all interface methods.
 *
 * @since PHP 8.3
 */
final class MockTransport implements UsbTransportInterface
{
    /** @var list<string> Queued responses returned by transferIn(). */
    private array $responseQueue = [];

    /** @var list<string> All data written via transferOut(). */
    private array $sentData = [];

    private bool $connected = false;

    // -------------------------------------------------------------------------
    // Test helpers
    // -------------------------------------------------------------------------

    /**
     * Enqueue a raw response packet for the next {@see transferIn()} call.
     *
     * @param string $response Raw bytes (including the 4-char status prefix).
     */
    public function queueResponse(string $response): void
    {
        $this->responseQueue[] = $response;
    }

    /**
     * Return all payloads written via {@see transferOut()}.
     *
     * @return list<string>
     */
    public function getSentData(): array
    {
        return $this->sentData;
    }

    /**
     * Clear all state for a clean test run.
     */
    public function clearState(): void
    {
        $this->responseQueue = [];
        $this->sentData      = [];
        $this->connected     = false;
    }

    // -------------------------------------------------------------------------
    // UsbTransportInterface
    // -------------------------------------------------------------------------

    #[Override]
    public function open(): void
    {
        $this->connected = true;
    }

    #[Override]
    public function close(): void
    {
        $this->connected = false;
    }

    #[Override]
    public function isConnected(): bool
    {
        return $this->connected;
    }

    #[Override]
    public function transferOut(string $data): void
    {
        $this->sentData[] = $data;
    }

    #[Override]
    public function transferIn(int $maxLength): string
    {
        if ($this->responseQueue === []) {
            throw new \UnderflowException('MockTransport: no more queued responses.');
        }
        $response = array_shift($this->responseQueue);
        return substr($response, 0, $maxLength);
    }

    #[Override]
    public function reset(): void
    {
        // No-op for mock
    }
}
